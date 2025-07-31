<div class="flex items-center justify-between">
  <div class="flex flex-col">
      <span class="text-sm font-medium">Mode RAG</span>
      <span class="text-xs text-gray-500">Enrichit les réponses avec le contexte des documents</span>
  </div>

  <!-- Toggle switch -->
  <button
      x-data="{ enabled: @js($enabled) }"
      @click="enabled = !enabled; $wire.toggle()"
      class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
      :class="{ 'bg-black': enabled, 'bg-gray-300': !enabled }"
      aria-pressed="enabled"
  >
      <span class="absolute right-2 flex items-center justify-center" aria-hidden="true">
          <span
              class="block h-2.5 w-2.5 rounded-full border transition-colors"
              :class="{ 'border-gray-500': enabled, 'border-white': !enabled }"
          ></span>
      </span>

      <span class="absolute left-3 flex items-center justify-center" aria-hidden="true">
          <span
              class="block h-3 w-0.5 rounded-sm transition-colors"
              :class="{ 'bg-white': enabled, 'bg-gray-500': !enabled }"
          ></span>
      </span>

      <span
          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
          :class="{ 'translate-x-6': enabled, 'translate-x-1': !enabled }"
      ></span>
  </button>
</div>