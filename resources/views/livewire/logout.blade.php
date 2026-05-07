<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();
    $this->redirect('/', navigate: false);
};
?>

<x-ui.button wire:click="logout" id="logout-button" variant="ghost" class="w-full justify-center px-3 py-2 text-sm text-destructive hover:bg-destructive/10 hover:text-destructive">
    {{ __('Log Out') }}
</x-ui.button> 
