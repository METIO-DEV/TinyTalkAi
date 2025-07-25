<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();
    $this->redirect('/', navigate: false);
};
?>

<x-danger-button wire:click="logout" class="w-full flex justify-center items-center px-4 py-2 text-sm text-custom-black dark:text-custom-white rounded-md">
    {{ __('Log Out') }}
</x-danger-button> 
