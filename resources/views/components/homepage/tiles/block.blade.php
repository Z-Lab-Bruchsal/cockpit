{{-- Tiles of a block type render the content block view of the same name with the tile settings. --}}
@include('components.homepage.blocks.'.str_replace('_', '-', $type), ['data' => $data, 'section' => $item->tile->section])
