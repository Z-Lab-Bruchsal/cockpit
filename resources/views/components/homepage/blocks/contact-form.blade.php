<div class="flex flex-col gap-4">
    <x-homepage.rich-text :html="$data['intro'] ?? null" />
    <x-homepage.contact-form :topic="$data['topic'] ?? null" />
</div>
