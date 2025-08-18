<!-- Composant pour la liste des modèles -->
<div class="[&::-webkit-scrollbar]:w-0 bg-custom-light dark:bg-custom-light-dark-mode dark:text-custom-white border-2 border-custom-white dark:border-none rounded-lg p-4 overflow-y-auto max-h-48
" id="model-container">
    <div class="flex flex-col gap-2" id="model-list">
        @if(isset($models) && count($models) > 0)
            @foreach($models as $model)
                <button type="button" 
                    class="model-button p-6 rounded-lg border transition-colors duration-200 w-full text-center "
                    data-model="{{ $model['name'] }}"
                    data-size="{{ isset($model['size']) ? $model['size'] : '' }}">
                    <div class="font-medium text-custom-black dark:text-custom-white">{{ explode(':', $model['name'])[0] }}</div>
                </button>
            @endforeach
        @else
            <div class="text-sm text-custom-black dark:text-custom-white py-2">Aucun modèle disponible</div>
        @endif
    </div>
</div>
