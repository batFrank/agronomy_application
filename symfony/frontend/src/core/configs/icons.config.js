import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

/* import specific icons */
import { faDotCircle, faSquareCheck, faTimes, faCaretUp, faCaretDown, faBars } from '@fortawesome/free-solid-svg-icons'

import { faCircle, faSquare } from '@fortawesome/free-regular-svg-icons'

/* add icons to the library */
library.add(faDotCircle, faCircle, faSquare, faSquareCheck, faTimes, faCaretUp, faCaretDown, faBars)
export default FontAwesomeIcon
