{{--
    Meme ecran que l'interface medecin, sous le fil d'Ariane du poste.

    Les composants sont partages, pas recopies : un correctif porte sur les
    deux interfaces, et un echographiste voit exactement le formulaire que
    voit un medecin. Ce qui differe, c'est la capacite qui l'a fait
    apparaitre, et la colonne signee en base, medecin ou personnel.
--}}
<div class="pile">
    <x-page-header
        :fil="['Poste', 'Nouveau patient']"
        titre="Nouveau patient"
        sous-titre="Enregistrer un patient qui n'a encore aucun dossier ici." />

    @livewire('reception.patient-registration-form', [], key('staff-patient-registration-form'))
</div>
