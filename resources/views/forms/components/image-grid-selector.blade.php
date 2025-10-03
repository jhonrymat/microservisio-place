{{-- resources/views/filament/forms/image-grid-selector.blade.php --}}

@php
    $images = $images ?? [];
    $field = $field ?? 'image_id';
    $columns = $columns ?? 3;
    $gridCols = match($columns) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-2 md:grid-cols-3',
        4 => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        default => 'grid-cols-3',
    };
@endphp

<div
    x-data="imageSelector(@js($field))"
    class="w-full"
>
    @if(count($images) > 0)
        <div class="grid {{ $gridCols }} gap-3">
            @foreach($images as $image)
                <div
                    @click="selectImage({{ $image['id'] }})"
                    :class="{
                        'ring-2 ring-primary-500 border-primary-500': selectedId === {{ $image['id'] }},
                        'border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700': selectedId !== {{ $image['id'] }}
                    }"
                    class="relative group cursor-pointer rounded-lg overflow-hidden border-2 transition-all duration-200"
                >
                    {{-- Imagen --}}
                    <div class="aspect-video bg-gray-100 dark:bg-gray-800 relative overflow-hidden">
                        <img
                            src="{{ $image['url'] }}"
                            alt="Opción {{ $loop->iteration }}"
                            class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                            loading="lazy"
                        >

                        {{-- Overlay oscuro al hover --}}
                        <div
                            class="absolute inset-0 bg-black transition-opacity duration-200"
                            :class="selectedId === {{ $image['id'] }} ? 'opacity-0' : 'opacity-0 group-hover:opacity-20'"
                        ></div>

                        {{-- Icono de zoom al hover (solo si no está seleccionado) --}}
                        <div
                            x-show="selectedId !== {{ $image['id'] }}"
                            class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                        >
                            <div class="bg-white dark:bg-gray-800 rounded-full p-2 shadow-lg">
                                <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Badge de selección --}}
                        <div
                            x-show="selectedId === {{ $image['id'] }}"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-90"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute top-2 right-2 bg-primary-600 text-white px-2.5 py-1 rounded-md text-xs font-medium shadow-lg flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Seleccionada</span>
                        </div>
                    </div>

                    {{-- Info de la imagen --}}
                    <div class="p-2 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                        @if(!empty($image['width']))
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ $image['width'] }} × {{ $image['height'] }} px
                            </p>
                        @endif
                        @if(!empty($image['author']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5" title="{{ $image['author'] }}">
                                © {{ $image['author'] }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Botón para limpiar selección --}}
        <div class="mt-4 flex items-center justify-between">
            <button
                type="button"
                @click="clearSelection()"
                x-show="selectedId !== null"
                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span>Usar imagen por defecto</span>
            </button>

            <div
                x-show="selectedId !== null"
                class="text-xs text-gray-500 dark:text-gray-400"
            >
                <span x-text="'ID: ' + selectedId"></span>
            </div>
        </div>

    @else
        <div class="text-center py-12 bg-gray-50 dark:bg-gray-900 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-700">
            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No hay imágenes disponibles</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Las imágenes se mostrarán después de descargarlas</p>
        </div>
    @endif
</div>

<script>
    function imageSelector(fieldName) {
        return {
            selectedId: null,
            fieldName: fieldName,

            init() {
                // Intentar obtener el valor inicial del campo hidden
                const hiddenField = document.querySelector(`input[name="data.${this.fieldName}"]`);
                if (hiddenField && hiddenField.value) {
                    this.selectedId = parseInt(hiddenField.value);
                }
            },

            selectImage(id) {
                this.selectedId = id;
                // Actualizar el campo hidden de Filament
                this.updateHiddenField(id);
            },

            clearSelection() {
                this.selectedId = null;
                this.updateHiddenField(null);
            },

            updateHiddenField(value) {
                // Buscar el campo hidden y actualizarlo
                const hiddenField = document.querySelector(`input[name="data.${this.fieldName}"]`);
                if (hiddenField) {
                    hiddenField.value = value || '';
                    // Disparar evento para que Filament/Livewire detecte el cambio
                    hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
                    hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Intentar actualizar vía wire:model si está disponible
                if (window.Livewire && hiddenField?.closest('[wire\\:id]')) {
                    const component = window.Livewire.find(
                        hiddenField.closest('[wire\\:id]').getAttribute('wire:id')
                    );
                    if (component) {
                        component.set(`data.${this.fieldName}`, value);
                    }
                }
            }
        }
    }
</script>
