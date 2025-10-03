{{-- resources/views/forms/components/image-selector.blade.php --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ selected: $wire.entangle('{{ $getStatePath() }}') }">
        @if(count($getImages()) > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($getImages() as $image)
                    <div
                        @class([
                            'relative group cursor-pointer rounded-lg overflow-hidden border-2 transition-all',
                            'border-primary-600 ring-2 ring-primary-600' => false,
                            'border-gray-200 dark:border-gray-700 hover:border-primary-400' => true,
                        ])
                        x-bind:class="{
                            'border-primary-600 ring-2 ring-primary-600': selected == {{ $image['id'] }},
                            'border-gray-200 dark:border-gray-700 hover:border-primary-400': selected != {{ $image['id'] }}
                        }"
                        wire:click="$set('{{ $getStatePath() }}', {{ $image['id'] }})"
                    >
                        {{-- Imagen --}}
                        <div class="aspect-video bg-gray-100 dark:bg-gray-800">
                            <img
                                src="{{ $image['url'] }}"
                                alt="Opción {{ $loop->iteration }}"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            >
                        </div>

                        {{-- Badge de selección --}}
                        <div
                            x-show="selected == {{ $image['id'] }}"
                            x-transition
                            class="absolute top-2 right-2 bg-primary-600 text-white px-2 py-1 rounded-md text-xs font-medium shadow-lg"
                        >
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ $getSelectedLabel() }}
                            </span>
                        </div>

                        {{-- Overlay al hover --}}
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all flex items-center justify-center">
                            <div
                                x-show="selected != {{ $image['id'] }}"
                                class="opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <div class="bg-white dark:bg-gray-800 rounded-full p-2 shadow-lg">
                                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        {{-- Info adicional --}}
                        @if(!empty($image['width']) || !empty($image['author']))
                            <div class="p-2 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                                @if(!empty($image['width']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $image['width'] }} × {{ $image['height'] }}
                                    </p>
                                @endif
                                @if(!empty($image['author']))
                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                        © {{ $image['author'] }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Botón para limpiar selección --}}
            <div class="mt-4">
                <button
                    type="button"
                    wire:click="$set('{{ $getStatePath() }}', null)"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                >
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Usar imagen por defecto
                    </span>
                </button>
            </div>
        @else
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p>No hay imágenes disponibles</p>
                <p class="text-xs mt-1">Las imágenes se mostrarán después de descargarlas</p>
            </div>
        @endif
    </div>
</x-dynamic-component>
