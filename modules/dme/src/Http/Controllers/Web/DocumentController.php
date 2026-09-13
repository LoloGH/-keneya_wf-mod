<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\MedicalDocument;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Services\Documents\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Documents médicaux (§28) et téléchargement contrôlé (§42).
 *
 * Aucun document n'est accessible par une URL de fichier : le disque est
 * privé et le contenu ne transite que par `download` / `preview`, après
 * vérification de MedicalDocumentPolicy. Chaque extraction est inscrite
 * au journal d'audit, ce qui permet de savoir qui a sorti quelle pièce
 * du dossier et quand.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentStorage $storage)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MedicalDocument::class);

        $documents = MedicalDocument::query()
            ->with(['patient:id,patient_number,first_name,last_name', 'uploader:id,name,first_name,last_name,title'])
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($inner) => $inner
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('document_number', 'like', '%'.$term.'%')
                    ->orWhereHas('patient', fn ($p) => $p->search($term))))
            ->orderByDesc('created_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::documents.index', [
            'documents' => $documents,
            'filters' => $request->only(['q', 'type']),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('create', MedicalDocument::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:'.implode(',', array_keys(MedicalDocument::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required',
                'file',
                'max:'.config('dme.documents.max_size_kb'),
                'mimes:'.implode(',', config('dme.documents.allowed_mimes')),
            ],
        ], [
            'file.mimes' => 'Format de fichier non autorisé.',
            'file.max' => 'Le fichier dépasse la taille maximale autorisée.',
        ], [
            'title' => 'titre',
            'type' => 'type de document',
            'file' => 'fichier',
        ]);

        $document = $this->storage->storeUploaded($patient, $request->file('file'), [
            'title' => $data['title'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Document '.$document->document_number.' ajouté au dossier.');
    }

    public function show(MedicalDocument $document): View
    {
        $this->authorize('view', $document);

        $document->load([
            'patient:id,patient_number,first_name,last_name',
            'uploader:id,name,first_name,last_name,title',
            'previousVersion:id,document_number,title,version',
        ]);

        return view('dme::documents.show', ['document' => $document]);
    }

    /**
     * Téléchargement contrôlé (§42).
     *
     * Le chemin physique n'apparaît jamais dans l'URL : seule la clé du
     * document est exposée, et la policy décide avant toute lecture disque.
     */
    public function download(MedicalDocument $document): Response
    {
        $this->authorize('download', $document);

        $contents = $this->storage->read($document);

        AuditLog::record(
            action: 'downloaded',
            subject: $document,
            patientId: $document->patient_id,
            description: 'A téléchargé '.$document->document_number,
        );

        return response($contents, 200, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($document).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Aperçu intégré (§28) : le document se regarde sans être téléchargé.
     *
     * PDF **et images** : un compte rendu d'échographie arrive le plus souvent
     * en photo ou en scan, et obliger le praticien à le télécharger pour le
     * lire laisse une copie du dossier sur chaque poste qui l'a consulté.
     *
     * Ce qui n'est pas dans {@see MedicalDocument::PREVIEWABLE_MIMES} reste
     * téléchargeable, jamais servi en ligne : un HTML ou un SVG téléversé
     * exécuterait son script sur le domaine du dossier. La liste est blanche
     * pour cette raison, et l'en-tête `Content-Security-Policy` interdit au
     * document de charger quoi que ce soit.
     */
    public function preview(MedicalDocument $document): Response
    {
        $this->authorize('download', $document);

        abort_unless(
            $document->isPreviewable(),
            404,
            'Ce type de document ne dispose pas d\'un aperçu intégré.',
        );

        AuditLog::record(
            action: 'viewed',
            subject: $document,
            patientId: $document->patient_id,
            description: 'A ouvert l\'aperçu de '.$document->document_number,
        );

        return response($this->storage->read($document), 200, array_filter([
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($document).'"',
            'X-Content-Type-Options' => 'nosniff',
            // Le bac à sable est réservé aux images. Un PDF est rendu par le
            // lecteur intégré du navigateur, qui exécute ses propres scripts :
            // le lui interdire n'afficherait plus rien.
            'Content-Security-Policy' => $document->isImage()
                ? "default-src 'none'; img-src 'self'; sandbox"
                : null,
        ], static fn ($valeur) => $valeur !== null));
    }

    /**
     * Nom de fichier assaini : le nom d'origine provient de l'utilisateur
     * et ne doit jamais pouvoir injecter d'en-tête HTTP.
     */
    private function safeFilename(MedicalDocument $document): string
    {
        $name = $document->original_name ?: $document->document_number;

        return preg_replace('/[^\w.\-]/u', '_', basename($name)) ?: $document->document_number;
    }
}
