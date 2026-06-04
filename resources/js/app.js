import Sort from '@alpinejs/sort';

// Register the Alpine "sort" plugin (x-sort) on Livewire's bundled Alpine instance
// so the kanban board supports accessible drag-and-drop reordering.
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(Sort);
});
