import './bootstrap';

import Alpine from 'alpinejs';
import invoiceForm from './invoice-form';
import recordForm from './record-form';
import returnForm from './return-form';
import settingsPreview from './settings-preview';

window.Alpine = Alpine;

Alpine.data('invoiceForm', invoiceForm);
Alpine.data('recordForm', recordForm);
Alpine.data('returnForm', returnForm);
Alpine.data('settingsPreview', settingsPreview);

Alpine.start();
