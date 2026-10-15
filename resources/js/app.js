import './bootstrap';

import Alpine from 'alpinejs';
import recordForm from './record-form';

window.Alpine = Alpine;

Alpine.data('recordForm', recordForm);

Alpine.start();
