import './bootstrap';

import Alpine from 'alpinejs';
import recordForm from './record-form';
import settingsPreview from './settings-preview';

window.Alpine = Alpine;

Alpine.data('recordForm', recordForm);
Alpine.data('settingsPreview', settingsPreview);

Alpine.start();
