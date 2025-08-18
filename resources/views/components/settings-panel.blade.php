<!-- Composant pour les réglages -->
<div class="bg-custom-light dark:bg-custom-light-dark-mode dark:text-custom-white border-2 border-custom-white dark:border-custom-white-dark-mode rounded-lg p-4 max-h-60 overflow-y-auto">
    <div class="space-y-4">
        <!-- Température -->
        <div>
            <label for="temperature" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">Température: <span id="temperature-value">{{ $temperature }}</span></label>
            <input type="range" id="temperature" name="temperature" min="0" max="2" step="0.1" value="{{ $temperature }}" 
                class="w-full h-2 bg-custom-mid rounded-lg appearance-none cursor-pointer"
                oninput="document.getElementById('temperature-value').textContent = this.value">
        </div>
        
        <!-- Tokens maximum -->
        <div>
            <label for="max-tokens" class="block text-sm font-medium text-custom-black dark:text-custom-white mb-1">Max Tokens: <span id="max-tokens-value">{{ $maxTokens }}</span></label>
            <input type="range" id="max-tokens" name="max_tokens" min="256" max="4096" step="128" value="{{ $maxTokens }}"
                class="w-full h-2 bg-custom-mid rounded-lg appearance-none cursor-pointer"
                oninput="document.getElementById('max-tokens-value').textContent = this.value">
        </div>
    </div>
</div>
