{{--
    Meme ecran que l'interface medecin, sous le fil d'Ariane du poste.

    Les composants sont partages, pas recopies : un correctif porte sur les
    deux interfaces, et un echographiste voit exactement le formulaire que
    voit un medecin. Ce qui differe, c'est la capacite qui l'a fait
    apparaitre, et la colonne signee en base, medecin ou personnel.
--}}
<div class="pile">
    <x-page-header
        :fil="['Poste', 'Visiteur']"
        titre="Visiteur"
        sous-titre="Enregistrer un accompagnateur ou un visiteur rattache a un patient." />

    @livewire('reception.visitor-registration-form', [], key('staff-visitor-registration-form'))
</div>
