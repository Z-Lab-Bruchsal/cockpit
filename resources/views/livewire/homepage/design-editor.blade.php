<div
    wire:ignore
    x-data="designEditor(@js($state), @js($roles), @js($hasDraft))"
    x-on:design-reload.window="window.location.reload()"
    class="fixed bottom-20 left-4 z-50 w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-white p-4 text-sm text-gray-900 shadow-2xl ring-1 ring-black/10"
    data-design-panel
>
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="font-bold">Farben gestalten</p>
        <div class="flex gap-1">
            <button type="button" x-on:click="undo()" x-bind:disabled="history.length === 0" class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30" title="Rückgängig">↶</button>
            <button type="button" x-on:click="redo()" x-bind:disabled="future.length === 0" class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-30" title="Wiederholen">↷</button>
        </div>
    </div>

    <template x-if="! selection">
        <p class="text-gray-600">Klicke ein Element auf der Seite an, um seine Farbe zu ändern. Links sind in diesem Modus ausgeschaltet.</p>
    </template>

    <template x-if="selection">
        <div class="flex flex-col gap-3">
            <div>
                <p class="text-xs text-gray-500">Ausgewählt</p>
                <p class="font-semibold" x-text="roles[selection.role]?.label ?? selection.role"></p>
            </div>

            <template x-if="selection.availableRoles.length > 1">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">Farbe für</span>
                    <select x-model="selection.role" x-on:change="refreshColor()" class="rounded-lg border-gray-300 py-1 text-sm">
                        <template x-for="role in selection.availableRoles" :key="role">
                            <option :value="role" x-text="roles[role]?.label ?? role"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-if="selection.elementId">
                <fieldset class="flex flex-col gap-1">
                    <legend class="mb-1 text-xs text-gray-500">Gilt für</legend>
                    <label class="flex items-center gap-2"><input type="radio" value="role" x-model="scope" x-on:change="refreshColor()"> alle Elemente dieser Art</label>
                    <label class="flex items-center gap-2"><input type="radio" value="element" x-model="scope" x-on:change="refreshColor()"> nur dieses Element</label>
                </fieldset>
            </template>

            <div class="flex items-center gap-2">
                <input type="color" x-bind:value="color" x-on:focus="beginEdit()" x-on:click="beginEdit()" x-on:input="setColor($event.target.value, false)" x-on:change="endEdit()" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                <input type="text" x-model.lazy="color" x-on:change="isHex(color) && setColor(color)" maxlength="7" class="w-28 rounded-lg border-gray-300 py-1 font-mono text-sm" aria-label="Farbwert">
                <button type="button" x-on:click="resetSelection()" class="ml-auto rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">Standard</button>
            </div>

            <p class="text-xs" x-bind:class="contrast() < 3 ? 'text-red-600' : 'text-gray-500'">
                Kontrast zu Weiß: <span x-text="contrast().toFixed(1)"></span>:1
                <span x-show="contrast() < 3">– als Textfarbe schwer lesbar</span>
            </p>
        </div>
    </template>

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
        <span class="text-xs text-gray-500" x-text="saving ? 'Speichert Entwurf …' : (dirty || hasDraft ? 'Entwurf – nur für dich sichtbar' : 'Keine Änderungen')"></span>
        <button type="button" x-on:click="discard()" class="ml-auto rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">Verwerfen</button>
        <button type="button" x-on:click="publish()" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">Veröffentlichen</button>
    </div>
</div>
