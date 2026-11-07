import './bootstrap';
import 'clockwork-browser/toolbar';
import { registerMoneyInput } from './money-input';

document.addEventListener('alpine:init', () => registerMoneyInput(window.Alpine));
