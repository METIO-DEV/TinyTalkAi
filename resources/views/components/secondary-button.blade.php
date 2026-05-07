<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-9 items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50']) }}>
    {{ $slot }}
</button>
