{{-- resources/views/filament/pages/gestionar-bloqueos.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Alerta informativa --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <span class="text-lg font-semibold">⚠️ ¿Qué son los bloqueos de campos?</span>
                </div>
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Los <strong>bloqueos de campos</strong> protegen información específica de ser sobrescrita durante las sincronizaciones automáticas con Google Places.
                </p>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>✅ <strong>Campo bloqueado:</strong> NO se actualiza en las sincronizaciones</li>
                    <li>🔓 <strong>Campo desbloqueado:</strong> Se actualiza automáticamente desde Google</li>
                </ul>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                    <strong>Ejemplo:</strong> Si un dueño actualizó su teléfono directamente en la plataforma, bloquea el campo "Teléfono" para evitar que se sobrescriba con el número antiguo de Google.
                </p>
            </div>
        </x-filament::section>

        {{-- Formulario --}}
        <form wire:submit="guardarBloqueos">
            {{ $this->form }}

            @if($listingSeleccionado)
                <div class="mt-6 flex justify-end gap-3">
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-check"
                        color="success"
                    >
                        Guardar Bloqueos
                    </x-filament::button>
                </div>
            @endif
        </form>

        {{-- Sección de ayuda --}}
        @if($listingSeleccionado)
            <x-filament::section
                collapsible
                collapsed
            >
                <x-slot name="heading">
                    💡 Guía Rápida
                </x-slot>

                <div class="prose dark:prose-invert max-w-none text-sm">
                    <h4 class="text-base font-semibold">Cuándo bloquear campos:</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                        <div class="bg-blue-50 dark:bg-blue-950 p-3 rounded-lg">
                            <h5 class="font-semibold text-blue-900 dark:text-blue-100">📋 Información Básica</h5>
                            <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1 mt-2">
                                <li>• Teléfono actualizado por el dueño</li>
                                <li>• Email específico de la plataforma</li>
                                <li>• Dirección con formato personalizado</li>
                            </ul>
                        </div>

                        <div class="bg-purple-50 dark:bg-purple-950 p-3 rounded-lg">
                            <h5 class="font-semibold text-purple-900 dark:text-purple-100">📝 Contenido</h5>
                            <ul class="text-xs text-purple-800 dark:text-purple-200 space-y-1 mt-2">
                                <li>• Descripción redactada por marketing</li>
                                <li>• Categorías específicas de tu plataforma</li>
                                <li>• Amenidades verificadas manualmente</li>
                            </ul>
                        </div>

                        <div class="bg-green-50 dark:bg-green-950 p-3 rounded-lg">
                            <h5 class="font-semibold text-green-900 dark:text-green-100">📸 Media</h5>
                            <ul class="text-xs text-green-800 dark:text-green-200 space-y-1 mt-2">
                                <li>• Fotos curadas manualmente</li>
                                <li>• Miniatura/portada personalizada</li>
                                <li>• Video promocional exclusivo</li>
                            </ul>
                        </div>

                        <div class="bg-orange-50 dark:bg-orange-950 p-3 rounded-lg">
                            <h5 class="font-semibold text-orange-900 dark:text-orange-100">⚙️ Otros</h5>
                            <ul class="text-xs text-orange-800 dark:text-orange-200 space-y-1 mt-2">
                                <li>• Redes sociales actualizadas</li>
                                <li>• SEO optimizado manualmente</li>
                                <li>• Rango de precios personalizado</li>
                            </ul>
                        </div>
                    </div>

                    <h4 class="text-base font-semibold mt-4">Estrategias recomendadas:</h4>
                    <ol class="text-xs space-y-1">
                        <li>1. <strong>Al importar:</strong> NO bloquear nada (permitir sincronización completa)</li>
                        <li>2. <strong>Después de editar:</strong> Bloquear solo el campo editado</li>
                        <li>3. <strong>Revisar periódicamente:</strong> Desbloquear si la info de Google es mejor</li>
                    </ol>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
