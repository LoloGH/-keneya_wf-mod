<div class="pile">
    <x-page-header
        :fil="['Accueil', 'Systeme', 'Utilisateurs']"
        titre="Comptes et acces"
        sous-titre="Qui peut se connecter, sous quel role. La creation d'un agent se fait dans Personnel." />

    @livewire('admin.user-directory', [], key('admin-user-directory'))
</div>
