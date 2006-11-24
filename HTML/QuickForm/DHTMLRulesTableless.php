<?php
/**
 * DHTML replacement for the standard JavaScript alert window for client-side
 * validation
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to wiesemann@php.net so we can send you a copy immediately.
 *
 * @category   HTML
 * @package    HTML_QuickForm_DHTMLRulesTableless
 * @author     Alexey Borzov <borz_off@cs.msu.su>
 * @author     Adam Daniel <adaniel1@eesus.jnj.com>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Justin Patrin <papercrane@gmail.com>
 * @author     Mark Wiesemann <wiesemann@php.net>
 * @copyright  2005-2006 The PHP Group
 * @license    http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/HTML_QuickForm_DHTMLRulesTableless
 */

require_once 'HTML/QuickForm.php';

/**
 * This is a DHTML replacement for the standard JavaScript alert window for
 * client-side validation of forms built with HTML_QuickForm
 *
 * @category   HTML
 * @package    HTML_QuickForm_DHTMLRulesTableless
 * @author     Alexey Borzov <borz_off@cs.msu.su>
 * @author     Adam Daniel <adaniel1@eesus.jnj.com>
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @author     Justin Patrin <papercrane@gmail.com>
 * @author     Mark Wiesemann <wiesemann@php.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/HTML_QuickForm_DHTMLRulesTableless
 */
class HTML_QuickForm_DHTMLRulesTableless extends HTML_QuickForm {
    // {{{ getValidationScript()

    /**
     * Returns the client side validation script
     *
     * The code here was copied from HTML_QuickForm and slightly modified to run rules per-element
     *
     * @access    public
     * @return    string    Javascript to perform validation, empty string if no 'client' rules were added
     */
    function getValidationScript()
    {
        if (empty($this->_rules) || empty($this->_attributes['onsubmit'])) {
            return '';
        }

        include_once('HTML/QuickForm/RuleRegistry.php');
        $registry =& HTML_QuickForm_RuleRegistry::singleton();
        $test = array();
        $js_escape = array(
            "\r"    => '\r',
            "\n"    => '\n',
            "\t"    => '\t',
            "'"     => "\\'",
            '"'     => '\"',
            '\\'    => '\\\\'
        );

        foreach ($this->_rules as $elementName => $rules) {
            foreach ($rules as $rule) {
                if ('client' == $rule['validation']) {
                    unset($element);

                    $dependent  = isset($rule['dependent']) && is_array($rule['dependent']);
                    $rule['message'] = strtr($rule['message'], $js_escape);

                    if (isset($rule['group'])) {
                        $group    =& $this->getElement($rule['group']);
                        // No JavaScript validation for frozen elements
                        if ($group->isFrozen()) {
                            continue 2;
                        }
                        $elements =& $group->getElements();
                        foreach (array_keys($elements) as $key) {
                            if ($elementName == $group->getElementName($key)) {
                                $element =& $elements[$key];
                                break;
                            }
                        }
                    } elseif ($dependent) {
                        $element   =  array();
                        $element[] =& $this->getElement($elementName);
                        foreach ($rule['dependent'] as $idx => $elName) {
                            $element[] =& $this->getElement($elName);
                        }
                    } else {
                        $element =& $this->getElement($elementName);
                    }
                    // No JavaScript validation for frozen elements
                    if (is_object($element) && $element->isFrozen()) {
                        continue 2;
                    } elseif (is_array($element)) {
                        foreach (array_keys($element) as $key) {
                            if ($element[$key]->isFrozen()) {
                                continue 3;
                            }
                        }
                    }

                    $test[$elementName][] = $registry->getValidationScript($element, $elementName, $rule);
                }
            }
        }
        $js = '
<script type="text/javascript">
//<![CDATA[
var lastElementName = "";
function qf_errorHandler(element, _qfMsg) {
  div = element.parentNode;
  var elementName = element.name;
  var bracketPos = element.name.search(/\[/);
  if (bracketPos != -1) {
    var elementName = element.name.slice(0, bracketPos);
  }
  if (_qfMsg != \'\') {
    span = document.createElement("span");
    span.className = "error";
    span.appendChild(document.createTextNode(_qfMsg.substring(3)));
    br = document.createElement("br");

    var errorDiv = document.getElementById(elementName + \'_errorDiv\');
    if (!errorDiv) {
      errorDiv = document.createElement("div");
      errorDiv.id = elementName + \'_errorDiv\';
    }
    while (errorDiv.firstChild) {
      errorDiv.removeChild(errorDiv.firstChild);
    }
    
    errorDiv.insertBefore(br, errorDiv.firstChild);
    errorDiv.insertBefore(span, errorDiv.firstChild);
    element.parentNode.insertBefore(errorDiv, element.parentNode.firstChild);

    if (div.className.substr(div.className.length - 6, 6) != " error"
        && div.className != "error") {
      div.className += " error";
    }

    lastElementName = elementName;
    return false;
  } else {
    if (lastElementName == elementName) {
      return true;
    }
    var errorDiv = document.getElementById(elementName + \'_errorDiv\');
    if (errorDiv) {
      errorDiv.parentNode.removeChild(errorDiv);
    }

    if (div.className.substr(div.className.length - 6, 6) == " error") {
      div.className = div.className.substr(0, div.className.length - 6);
    } else if (div.className == "error") {
      div.className = "";
    }

    lastElementName = elementName;
    return true;
  }
}';
        $validateJS = '';
        foreach ($test as $elementName => $jsArr) {
            // remove group element part of the element name to avoid JS errors
            $singleElementName = $elementName;
            $shortNameForJS = $elementName;
            $bracketPos = strpos($elementName, '[');
            if ($bracketPos !== false) {
                $shortNameForJS = str_replace(array('[', ']'), '__', $elementName);
                $singleElementName = substr($elementName, 0, $bracketPos);
                $groupElementName = substr($elementName, $bracketPos + 1, -1);
            }
            $js .= '
function validate_' . $this->_attributes['id'] . '_' . $shortNameForJS . '(element) {
  var value = \'\';
  var errFlag = new Array();
  var _qfGroups = {};
  var _qfMsg = \'\';
  var frm = element.parentNode;
  while (frm && frm.nodeName != "FORM") {
    frm = frm.parentNode;
  }
' . join("\n", $jsArr) . '
  return qf_errorHandler(element, _qfMsg);
}
';
            unset($element);
            $element =& $this->getElement($singleElementName);
            $elementNameForJS = 'frm.elements[\'' . $elementName . '\']';
            if ($element->getType() === 'group' && $singleElementName === $elementName) {
                $elementNameForJS = 'document.getElementById(\'' . $element->_elements[0]->getAttribute('id') . '\')';
            }
            $validateJS .= '
  ret = validate_' . $this->_attributes['id'] . '_' . $shortNameForJS . '('. $elementNameForJS . ') && ret;';
            if ($element->getType() !== 'group') {  // not a group
                $valFunc = 'validate_' . $this->_attributes['id'] . '_' . $elementName . '(this)';
                $onBlur = $element->getAttribute('onBlur');
                $onChange = $element->getAttribute('onChange');
                $element->updateAttributes(array('onBlur' => $onBlur . $valFunc,
                                                 'onChange' => $onChange . $valFunc));
            } else {  // group
                $elements =& $element->getElements();
                for ($i = 0; $i < count($elements); $i++) {
                    if ($elements[$i]->getAttribute('name') == $groupElementName) {
                        $valFunc = 'validate_' . $this->_attributes['id'] . '_' . $shortNameForJS . '(this)';
                        $onBlur = $elements[$i]->getAttribute('onBlur');
                        $onChange = $elements[$i]->getAttribute('onChange');
                        $elements[$i]->updateAttributes(array('onBlur'   => $onBlur . $valFunc,
                                                              'onChange' => $onChange . $valFunc));
                    }
                }
            }
        }
        $js .= '
function validate_' . $this->_attributes['id'] . '(frm) {
  var ret = true;
' . $validateJS . ';
  return ret;
}
//]]>
</script>';
        return $js;
    } // end func getValidationScript

    // }}}

    function display() {
        $this->getValidationScript();
        return parent::display();
    }
}

?>