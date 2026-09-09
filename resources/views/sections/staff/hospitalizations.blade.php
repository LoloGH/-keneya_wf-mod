{{--
    Meme ecran que l'interface medecin, sous le fil d'Ariane du poste.

    Les composants sont partages, pas recopies : un correctif porte sur les
    deux interfaces, et un echographiste voit exactement le formulaire que
    voit un medecin. Ce qui differe, c'est la capacite qui l'a fait
    apparaitre — et la colonne signee en base, medecin ou personnel.
--}}
<div class="pile">
    <x-page-header
        :fil="['Poste', 'Patients hospitalises']"
        titre="Patients hospitalises"
        sous-titre="Les patients admis dans votre service et leur planning de soins." />

    @livewire('service.hospitalizations', ['serviceId' => $serviceId], key('staff-hospitalizations-'.$serviceId))
</div>
