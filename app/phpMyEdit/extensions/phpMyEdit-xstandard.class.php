<?php

/*
 * phpMyEdit - instant MySQL table editor and code generator
 *
 * extensions/phpMyEdit-xstandard.class.php - phpMyEdit Xstandard extension
 * ____________________________________________________________
 *
 * Developed by Ondrej Jombik <nepto@platon.sk>
 * Copyright (c) 2006 Platon Group, http://platon.sk/
 * All rights reserved.
 *
 * See README file for more information about this software.
 * See COPYING file for license information.
 *
 * Download the latest version from
 * http://platon.sk/projects/phpMyEdit/
 */

/* $Platon: phpMyEdit/extensions/phpMyEdit-xstandard.class.php,v 1.4 2007/01/26 11:24:26 nepto Exp $ */

require_once dirname(__FILE__).'/../phpMyEdit.class.php';

class phpMyEdit_xstandard extends phpMyEdit
{
	function form_begin() /* {{{ */
	{
		$page_name = htmlspecialchars($this->page_name);
		if ($this->add_operation() || $this->change_operation() || $this->copy_operation()
				|| $this->view_operation() || $this->delete_operation()) {
			$field_to_tab = array();
			for ($tab = $k = $this->cur_tab = 0; $k < $this->num_fds; $k++) {
				if (isset($this->fdd[$k]['tab'])) {
					if ($tab == 0 && $k > 0) {
						$this->tabs[0] = 'PMEtab0';
						$this->cur_tab = 1;
						$tab++;
					}
					if (is_array($this->fdd[$k]['tab'])) {
						$this->tabs[$tab] = @$this->fdd[$k]['tab']['name'];
						$this->fdd[$k]['tab']['default'] && $this->cur_tab = $tab;
					} else {
						$this->tabs[$tab] = @$this->fdd[$k]['tab'];
					}
					$tab++;
				}
				$field_to_tab[$k] = max(0, $tab - 1);
			}
			if (preg_match('/^'.$this->dhtml['prefix'].'tab(\d+)$/', $this->get_sys_cgi_var('cur_tab'), $parts)) {
				$this->cur_tab = $parts[1];
			}
			if ($this->tabs_enabled()) {
				// initial TAB styles
				echo '<style type="text/css" media="screen">',"\n";
				for ($i = 0; $i < count($this->tabs); $i++) {
					echo '	#'.$this->dhtml['prefix'].'tab',$i,' { display: ';
					echo (($i == $this->cur_tab || $this->tabs[$i] == 'PMEtab0' ) ? 'block' : 'none') ,'; }',"\n";
				}
				echo '</style>',"\n";
				// TAB javascripts
				echo '<script type="text/javascript"><!--',"\n\n";
				$css_class_name1 = $this->getCSSclass('tab', $position);
				$css_class_name2 = $this->getCSSclass('tab-selected', $position);
				echo 'var '.$this->js['prefix'].'cur_tab  = "'.$this->dhtml['prefix'].'tab',$this->cur_tab,'";

function '.$this->js['prefix'].'show_tab(tab_name)
{';
				if ($this->nav_up()) {
					echo '
	document.getElementById('.$this->js['prefix'].'cur_tab+"_up_label").className = "',$css_class_name1,'";
	document.getElementById('.$this->js['prefix'].'cur_tab+"_up_link").className = "',$css_class_name1,'";
	document.getElementById(tab_name+"_up_label").className = "',$css_class_name2,'";
	document.getElementById(tab_name+"_up_link").className = "',$css_class_name2,'";';
				}
				if ($this->nav_down()) {
					echo '
	document.getElementById('.$this->js['prefix'].'cur_tab+"_down_label").className = "',$css_class_name1,'";
	document.getElementById('.$this->js['prefix'].'cur_tab+"_down_link").className = "',$css_class_name1,'";
	document.getElementById(tab_name+"_down_label").className = "',$css_class_name2,'";
	document.getElementById(tab_name+"_down_link").className = "',$css_class_name2,'";';
				}
				echo '
	document.getElementById('.$this->js['prefix'].'cur_tab).style.display = "none";
	document.getElementById(tab_name).style.display = "block";
	'.$this->js['prefix'].'cur_tab = tab_name;
	document.'.$this->cgi['prefix']['sys'].'form.'.$this->cgi['prefix']['sys'].'cur_tab.value = tab_name;
}',"\n\n";
				echo '// --></script>', "\n";
			}
		}

		if ($this->add_operation() || $this->change_operation() || $this->copy_operation()) {
			$first_required = true;
			for ($k = 0; $k < $this->num_fds; $k++) {
				if ($this->displayed[$k] && ! $this->readonly($k) && ! $this->hidden($k)
						&& ($this->fdd[$k]['js']['required'] || isset($this->fdd[$k]['js']['regexp'])
							/* <XSTANDARD> */
							|| isset($this->fdd[$k]['textarea']['xstandard'])
							/* </XSTANDARD> */
							)) {
					if ($first_required) {
				 		$first_required = false;
						echo '<script type="text/javascript"><!--',"\n";
						echo '
function '.$this->js['prefix'].'trim(str)
{
	while (str.substring(0, 1) == " "
			|| str.substring(0, 1) == "\\n"
			|| str.substring(0, 1) == "\\r")
	{
		str = str.substring(1, str.length);
	}
	while (str.substring(str.length - 1, str.length) == " "
			|| str.substring(str.length - 1, str.length) == "\\n"
			|| str.substring(str.length - 1, str.length) == "\\r")
	{
		str = str.substring(0, str.length - 1);
	}
	return str;
}

function '.$this->js['prefix'].'form_control(theForm)
{',"\n";
					}
					if ($this->col_has_values($k)) {
						$condition = 'theForm.'.$this->cgi['prefix']['data'].$this->fds[$k].'.selectedIndex == -1';
						$multiple  = $this->col_has_multiple_select($k);
					} else {
						$condition = '';
						$multiple  = false;
						if ($this->fdd[$k]['js']['required']) {
							$condition = $this->js['prefix'].'trim(theForm.'.$this->cgi['prefix']['data'].$this->fds[$k].'.value) == ""';
						}
						if (isset($this->fdd[$k]['js']['regexp'])) {
							$condition .= (strlen($condition) > 0 ? ' || ' : '');
							$condition .= sprintf('!(%s.test('.$this->js['prefix'].'trim(theForm.%s.value)))',
									$this->fdd[$k]['js']['regexp'], $this->cgi['prefix']['data'].$this->fds[$k]);
						}
						/* <XSTANDARD> */
						if ($this->fdd[$k]['textarea']['xstandard']) {
							$condition = '0';
							echo '
	document.getElementById(\''.$this->cgi['prefix']['data'].$this->fds[$k].'_xstandard\').EscapeUnicode = true;
	document.getElementById(\''.$this->cgi['prefix']['data'].$this->fds[$k].'\').value = document.getElementById(\''.$this->cgi['prefix']['data'].$this->fds[$k].'_xstandard\').value;
';
						}
						/* </XSTANDARD> */
					}

					/* Multiple selects have their name like ``name[]''.
					   It is not possible to work with them directly, because
					   theForm.name[].something will result into JavaScript
					   syntax error. Following search algorithm is provided
					   as a workaround for this.
					 */
					if ($multiple) {
						echo '
	multiple_select = null;
	for (i = 0; i < theForm.length; i++) {
		if (theForm.elements[i].name == "',$this->cgi['prefix']['data'].$this->fds[$k],'[]") {
			multiple_select = theForm.elements[i];
			break;
		}
	}
	if (multiple_select != null && multiple_select.selectedIndex == -1) {';
					} else {
						echo '
	if (',$condition,') {';
					}
					echo '
		alert("';
					if (isset($this->fdd[$k]['js']['hint'])) {
						echo htmlspecialchars($this->fdd[$k]['js']['hint']);
					} else {
						echo $this->labels['Please enter'],' ',$this->fdd[$k]['name'],'.';
					}
					echo '");';
					if ($this->tabs_enabled() && $field_to_tab[$k] >= $this->cur_tab) {
						echo '
		'.$this->js['prefix'].'show_tab("'.$this->dhtml['prefix'].'tab',$field_to_tab[$k],'");';
					}
					echo '
		theForm.',$this->cgi['prefix']['data'].$this->fds[$k],'.focus();
		return false;
	}',"\n";
				}
			}
			if (! $first_required) {
				echo '
	return true;
}',"\n\n";
				echo '// --></script>', "\n";
			}
		}

		if ($this->filter_operation()) {
				echo '<script type="text/javascript"><!--',"\n";
				echo '
function '.$this->js['prefix'].'filter_handler(theForm, theEvent)
{
	var pressed_key = null;
	if (theEvent.which) {
		pressed_key = theEvent.which;
	} else {
		pressed_key = theEvent.keyCode;
	}
	if (pressed_key == 13) { // enter pressed
		theForm.submit();
		return false;
	}
	return true;
}',"\n\n";
				echo '// --></script>', "\n";
		}

		if ($this->display['form']) {
			echo '<form class="',$this->getCSSclass('form'),'" method="post"';
			echo ' action="',$page_name,'" name="'.$this->cgi['prefix']['sys'].'form">',"\n";
		}
		return true;
	} /* }}} */

	function display_add_record() /* {{{ */
	{
		for ($tab = 0, $k = 0; $k < $this->num_fds; $k++) {
			if (isset($this->fdd[$k]['tab']) && $this->tabs_enabled() && $k > 0) {
				$tab++;
				echo '</table>',"\n";
				echo '</div>',"\n";
				echo '<div id="'.$this->dhtml['prefix'].'tab',$tab,'">',"\n";
				echo '<table class="',$this->getCSSclass('main'),'" summary="',$this->tb,'">',"\n";
			}
			if (! $this->displayed[$k]) {
				continue;
			}
			if ($this->hidden($k)) {
				echo $this->htmlHiddenData($this->fds[$k], $this->fdd[$k]['default']);
				continue;
			}
			$css_postfix    = @$this->fdd[$k]['css']['postfix'];
			$css_class_name = $this->getCSSclass('input', null, 'next', $css_postfix);
			$escape			= isset($this->fdd[$k]['escape']) ? $this->fdd[$k]['escape'] : true;
			echo '<tr class="',$this->getCSSclass('row', null, true, $css_postfix),'">',"\n";
			echo '<td class="',$this->getCSSclass('key', null, true, $css_postfix),'">';
			echo $this->fdd[$k]['name'],'</td>',"\n";
			echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
			echo $this->getColAttributes($k),">\n";
			if ($this->col_has_values($k)) {
				$vals       = $this->set_values($k);
				$selected   = @$this->fdd[$k]['default'];
				$multiple   = $this->col_has_multiple($k);
				$readonly   = $this->readonly($k);
				$strip_tags = true;
				//$escape     = true;
				if ($this->col_has_checkboxes($k) || $this->col_has_radio_buttons($k)) {
					echo $this->htmlRadioCheck($this->cgi['prefix']['data'].$this->fds[$k],
							$css_class_name, $vals, $selected, $multiple, $readonly,
							$strip_tags, $escape);
				} else {
					echo $this->htmlSelect($this->cgi['prefix']['data'].$this->fds[$k],
							$css_class_name, $vals, $selected, $multiple, $readonly,
							$strip_tags, $escape);
				}
			/* <XSTANDARD> */
			} elseif (isset ($this->fdd[$k]['textarea'])
					&& isset ($this->fdd[$k]['textarea']['xstandard'])) {
				$name = $this->cgi['prefix']['data'].$this->fds[$k];
				echo '<input type="hidden" name="',$name,'" id="',$name,'" />',"\n";
				echo '<object type="application/x-xstandard" id="',$name,'_xstandard"';
				$height = '300';  // default value
				$width  = '100%'; // default value
				$params = null;
				if (is_array($this->fdd[$k]['textarea']['xstandard'])) {
					foreach ($this->fdd[$k]['textarea']['xstandard'] as $key => $val) {
						if (! strcasecmp($key, 'height')) {
							$height = $val;
						} else if (! strcasecmp($key, 'width')) {
							$width = $val;
						} else {
							$params .= '<param name="'.$key.'" value="'.$val.'" />'."\n";
						}
					}
				}
				echo " width=\"$width\" height=\"$height\" />\n$params";
				echo '</object>',"\n";
			/* </XSTANDARD> */
			} elseif (isset ($this->fdd[$k]['textarea'])) {
				echo '<textarea class="',$css_class_name,'" name="',$this->cgi['prefix']['data'].$this->fds[$k],'"';
				echo ($this->readonly($k) ? ' disabled="disabled"' : '');
				if (intval($this->fdd[$k]['textarea']['rows']) > 0) {
					echo ' rows="',$this->fdd[$k]['textarea']['rows'],'"';
				}
				if (intval($this->fdd[$k]['textarea']['cols']) > 0) {
					echo ' cols="',$this->fdd[$k]['textarea']['cols'],'"';
				}
				if (isset($this->fdd[$k]['textarea']['wrap'])) {
					echo ' wrap="',$this->fdd[$k]['textarea']['wrap'],'"';
				} else {
					echo ' wrap="virtual"';
				}
				echo '>';
				if($escape) echo htmlspecialchars($this->fdd[$k]['default']);
				else echo $this->fdd[$k]['default'];
				echo '</textarea>',"\n";
			} elseif ($this->col_has_php($k)) {
				echo include($this->fdd[$k]['php']);
			} else {
				// Simple edit box required
				$size_ml_props = '';
				$maxlen = intval($this->fdd[$k]['maxlen']);
				$size   = isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size'] : min($maxlen, 60); 
				$size   && $size_ml_props .= ' size="'.$size.'"';
				$maxlen && $size_ml_props .= ' maxlength="'.$maxlen.'"';
				echo '<input class="',$css_class_name,'" ';
				echo ($this->password($k) ? 'type="password"' : 'type="text"');
				echo ($this->readonly($k) ? ' disabled="disabled"' : '');
				echo ' name="',$this->cgi['prefix']['data'].$this->fds[$k],'"';
				echo $size_ml_props,' value="';
				if($escape) echo htmlspecialchars($this->fdd[$k]['default']);
			    else echo $this->fdd[$k]['default'];
				echo '" />';
			}
			echo '</td>',"\n";
			if ($this->guidance) {
				$css_class_name = $this->getCSSclass('help', null, true, $css_postfix);
				$cell_value     = $this->fdd[$k]['help'] ? $this->fdd[$k]['help'] : '&nbsp;';
				echo '<td class="',$css_class_name,'">',$cell_value,'</td>',"\n";
			}
			echo '</tr>',"\n";
		}
	} /* }}} */

	function display_change_field($row, $k) /* {{{ */ 
	{
		$css_postfix    = @$this->fdd[$k]['css']['postfix'];
		$css_class_name = $this->getCSSclass('input', null, true, $css_postfix);
		$escape         = isset($this->fdd[$k]['escape']) ? $this->fdd[$k]['escape'] : true;
		echo '<td class="',$this->getCSSclass('value', null, true, $css_postfix),'"';
		echo $this->getColAttributes($k),">\n";
		if ($this->col_has_values($k)) {
			$vals       = $this->set_values($k);
			$multiple   = $this->col_has_multiple($k);
			$readonly   = $this->readonly($k);
			$strip_tags = true;
			//$escape     = true;
			if ($this->col_has_checkboxes($k) || $this->col_has_radio_buttons($k)) {
				echo $this->htmlRadioCheck($this->cgi['prefix']['data'].$this->fds[$k],
						$css_class_name, $vals, $row["qf$k"], $multiple, $readonly,
						$strip_tags, $escape);
			} else {
				echo $this->htmlSelect($this->cgi['prefix']['data'].$this->fds[$k],
						$css_class_name, $vals, $row["qf$k"], $multiple, $readonly,
						$strip_tags, $escape);
			}
		/* <XSTANDARD> */
		} elseif (isset ($this->fdd[$k]['textarea'])
				&& isset ($this->fdd[$k]['textarea']['xstandard'])) {
			$name = $this->cgi['prefix']['data'].$this->fds[$k];
			echo '<input type="hidden" name="',$name,'" id="',$name,'" />',"\n";
			echo '<object type="application/x-xstandard" id="',$name,'_xstandard"';
			$height = '300';  // default value
			$width  = '100%'; // default value
			$params = null;
			if (is_array($this->fdd[$k]['textarea']['xstandard'])) {
				foreach ($this->fdd[$k]['textarea']['xstandard'] as $key => $val) {
					if (! strcasecmp($key, 'height')) {
						$height = $val;
					} else if (! strcasecmp($key, 'width')) {
						$width = $val;
					} else {
						$params .= '<param name="'.$key.'" value="'.$val.'" />'."\n";
					}
				}
			}
			echo " width=\"$width\" height=\"$height\" />\n$params";
			echo '<param name="Value" value="',htmlspecialchars($row["qf$k"]),'" />',"\n";
			echo '</object>',"\n";
		/* </XSTANDARD> */
		} elseif (isset($this->fdd[$k]['textarea'])) {
			echo '<textarea class="',$css_class_name,'" name="',$this->cgi['prefix']['data'].$this->fds[$k],'"';
			echo ($this->readonly($k) ? ' disabled="disabled"' : '');
			if (intval($this->fdd[$k]['textarea']['rows']) > 0) {
				echo ' rows="',$this->fdd[$k]['textarea']['rows'],'"';
			}
			if (intval($this->fdd[$k]['textarea']['cols']) > 0) {
				echo ' cols="',$this->fdd[$k]['textarea']['cols'],'"';
			}
			if (isset($this->fdd[$k]['textarea']['wrap'])) {
				echo ' wrap="',$this->fdd[$k]['textarea']['wrap'],'"';
			} else {
				echo ' wrap="virtual"';
			}
			echo '>';
			if($escape) echo htmlspecialchars($row["qf$k"]);
			else echo $row["qf$k"];
			echo '</textarea>',"\n";
		} elseif ($this->col_has_php($k)) {
			echo include($this->fdd[$k]['php']);
		} else {
			$size_ml_props = '';
			$maxlen = intval($this->fdd[$k]['maxlen']);
			$size   = isset($this->fdd[$k]['size']) ? $this->fdd[$k]['size'] : min($maxlen, 60); 
			$size   && $size_ml_props .= ' size="'.$size.'"';
			$maxlen && $size_ml_props .= ' maxlength="'.$maxlen.'"';
			echo '<input class="',$css_class_name,'" type="text" ';
			echo ($this->readonly($k) ? 'disabled="disabled" ' : '');
			echo 'name="',$this->cgi['prefix']['data'].$this->fds[$k],'" value="';
			if($escape) echo htmlspecialchars($row["qf$k"]);
			else echo $row["qf$k"];
			echo '" />',"\n";
		}
		echo '</td>',"\n";
	} /* }}} */

}

/* Modeline for ViM {{{
 * vim: set ts=4:
 * vim600: fdm=marker fdl=0 fdc=0:
 * }}} */

?>
