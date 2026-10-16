// Recovery after a run that died before its teardown: `npm run restore`.
import { cleanup, restoreCaptcha } from './site.mjs';

console.log(restoreCaptcha() ? 'captcha field restored to its recorded state' : 'captcha field left as it is: no readable state');
console.log('cleanup:', cleanup());
