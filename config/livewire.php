<?php

return [
    'temporary_file_upload' => [
        // Livewire validates the temporary upload before the component rule runs.
        'rules' => ['required', 'file', 'max:30720'],
    ],
];
