<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: datefield.inc.php,v 1.3 2006/03/10 01:29:06 jjyun Exp $
//

/* [��ά������]
 * ����������������դ��ե�������󶡥ץ饰���� 
 *    for pukiwiki-1.4.6
 *
 * �������Ϥ�Ԥ碌�����ƥ����ȥե�����ɤȡ�
 * �������Ϥ�Ԥ�����Υ���������ɽ������ܥ�����󶡤��ޤ���
 * ���������ˤ���������Ϥˤ��,�����ڡ����ι������Ԥ��ޤ���
 * ���������ؤΰ����ˤϡ��ƥ����ȥե�����ɤؤ�������
 * ���ս�(�ǥե������(�֥�󥯲�ǽ),[���ս�����])
 * 
 * ���¡�JavaScript ��ȤäƤ���Τ���,
 * Javascript�����ѤǤ���Ķ��Ǥʤ����ư���ޤ���
 * 
 * �֥饦��¦�������¾�ˡ�������¦������Ȥ���
 * pukiwiki.ini.php �� PKWK_ALLOW_JAVASCRIPT ��ʲ�������ˤ���ɬ�פ�����ޤ�
 *   define('PKWK_ALLOW_JAVASCRIPT', 1);     // 0 or 1
 */ 

// ������Υ���ɻ��ˡ��Խ��ս��ɽ���ս��ܤ�
// ͭ���ˤ�����ˤϡ�TRUE , ̵���ˤ�����ˤ� FALSE �����
define('DATEFIELD_JUMP_TO_MODIFIED_PLACE',FALSE); // TRUE or FALSE
// �⡼���ѹ���Ŭ�Ѥ���
define('DATEFIELD_APPLY_MODECHANGE',TRUE); // TRUE or FALSE

function plugin_datefield_init()
{
	$cfg = array(
			'_datefield_cfg' => array (
				'editImage'  => 'paraedit.png',
				'referImage' => 'close.png',
				)
			);
	set_plugin_messages($cfg);

	switch( LANG ) 
	{
	case 'ja' :
	  $msg = plugin_datefield_init_ja();
	default:
	  $msg = plugin_datefield_init_en();
	}
	set_plugin_messages($msg);
}

function plugin_datefield_init_ja()
{
	$msg = array(
		'_datefield_msg' => array(
			'format_not_effective'        => "���ս�ʸ���� %s �˥�������ʸ��(&nbsp;&#039;&nbsp;&quot;&nbsp;)����Ѥ��ʤ��Ǥ���������" ,
			'input_pattern_not_effective' =>  "�����ͤ����ս� %s �ȹ��פ��ޤ���<br />"
												+ "����ѥǥ��󥰤��θ���Ƥ���������",
			'datecheck_irregular_error' => "���ճ�ǧ�������곰���顼�Ǥ���<br />" 
												+ "��ǧ�о�ʸ����: %s <br />"
												+ "���ս�ʸ����: %s <br />"
												+ "�����ѿ�ʸ����: %s <br />"
												+ "�ѡ����񼰾���: %s <br />"
												+ "�ɤ߼�����:year = %s, month = %s, day = %s<br />",
			'datecheck_not_effective_month' => "��λ��� %s ���̾��������ͤ��鳰��Ƥ��ޤ���", 
			'datecheck_not_effective_day'   => "���դλ��� %s ���̾��������ͤ��鳰��Ƥ��ޤ���", 
			'datecheck_not_effective_date'  => "�������� %s ����Ŭ�ڤǤ���",
			)
		);
	return $msg;
}

function plugin_datefield_init_en()
{
	$msg = array(
		'_datefield_msg' => array(
			'format_not_effective'        => "You should not use quote character(&nbsp;&#039;&nbsp;&quot;&nbsp;) "
												+ "in the date format string( = %s)." ,
			'input_pattern_not_effective' => "It doesn't match input value with date format (= %s).<br />"
												+ "Consider 0 padding, please.",
			'datecheck_irregular_error'   => "A error beyond assumptions occurred when it confirmed input value with date format.<br />" 
												+ "string of input value       : %s <br />"
												+ "string of the date format   : %s <br />"
												+ "string valuable for receive : %s <br />"
												+ "state of parse format       : %s <br />"
												+ "state of reading            : year = %s, month = %s, day = %s<br />",
			'datecheck_not_effective_month' => "Month value of the input( = %s ) is out range of month.",
			'datecheck_not_effective_day'   => "Date value of the input( = %s ) is out range of date.",
			'datecheck_not_effective_date'  => "Input value ( = %s ) is invalid.",
			)
		);
	return $msg;
}

function plugin_datefield_action() {
    global $script, $vars;
	check_editable($vars['refer'], true, true);
	
	$number = 0;
	$pagedata = '';
	$pagedata_old  = get_source($vars['refer']);
	
	foreach($pagedata_old as $line)
	{
		if (! preg_match('/^(?:\/\/| )/', $line) &&
			preg_match_all('/(?:#datefield\(([^\)]*)\))/',
						   $line, $matches, PREG_SET_ORDER))
		{
			$paddata = preg_split('/#datefield\([^\)]*\)/', $line);
			$line = $paddata[0];
	
			foreach($matches as $i => $match)
			{
				$opt = $match[1];
				if ($vars['number'] == $number++)
				{
					//�������åȤΥץ饰������ʬ
					$para_array = preg_split('/,/',$opt);
					$errmsg = plugin_datefield_chkFormat($vars['infield'],$para_array[1]);
					if( strlen($errmsg) > 0 )
					{
						plugin_datefield_outputErrMsg($vars['refer'], $errmsg);
					}	    
	    
					$opt = preg_replace('/[^,]*/', $vars['infield'], $opt, 1);
				}
				$line .= "#datefield($opt)" . $paddata[$i+1];
			}
		}
		$pagedata .= $line;
	}

	page_write($vars['refer'], $pagedata); 
	if( DATEFIELD_JUMP_TO_MODIFIED_PLACE  && $pagedata != '' )
	{
		header("Location: $script?".rawurlencode($vars['refer'])."#datefield_no_".$vars['number']);
		exit;
	}
	return array('msg' => '', 'body' => '');
}

/* * function plugin_datefield_chkFormat($chkedStr, $formatStr) 
 * ����(��ǧ�о�)ʸ��������ս�ʸ����γ�ǧ��Ԥ�
 * ���꤬�ʤ���ж�ʸ������Զ�礬����Ф������Ƥ򼨤�ʸ������֤�
 */
function plugin_datefield_chkFormat($chkedStr, $formatStr)
{
	global $_datefield_msg;
	
	if( strlen($chkedStr) == 0 )
	  return "";

	if( strlen($formatStr) == 0) $formatStr='YYYY/MM/DD';
	$formatReg = $formatStr;

	/* ��������ʸ�� ��¸�߳�ǧ */
	if(preg_match('/^.*[\'\"].*$/',$formatReg) ) /* match character..." ' */ 
	{ 
		$errmsg = sprintf($_datefield_msg['format_not_effective'], $formatStr);
		return $errmsg;
	}

	/* �����ͤ����ս񼰤Ȥ���� */
	$formatReg = preg_replace('/\//','\\/',$formatReg);
	$formatReg = '/^' . preg_replace('/[YMD]/i','\\d',$formatReg) .'$/';
	if( ! preg_match($formatReg,$chkedStr) )
	{
		$errmsg = sprintf($_datefield_msg['input_pattern_not_effective'], $formatStr);
		return $errmsg;
	}

	$date = plugin_datefield_getDate($chkedStr, $formatStr);
	$year  = $date['year'];
	$month = $date['month'];
	$day   = $date['day'];

	if( $year == -1 and $month == -1 and $day == -1)
	{
		$errmsg = sprintf($_datefield_msg['datecheck_irregular_error'],
						  $chkedStr, $formatStr, $date['dateArgs'], $date['parseStr'],
						  $year, $month, $day);
		return $errmsg;
	}
	else if($month <= 0 or $month > 12)
	{
		/* ��λ����ɬ�� */
		$errmsg = sprintf($_datefield_msg['datecheck_not_effective_month'], $chkedStr );
		return $errmsg;
	}
	else
	{
		/* ����꤬������� */
		if( $day > 31)
		{
				$errmsg = sprintf($_datefield_msg['datecheck_not_effective_day'], $chkedStr );
				return $errmsg;
		}
		else
		{
				/* ���꤬�ʤ����� ��֤��� */
				if($year == -1) $year = date("Y",time());
				if($day  == -1) $day  = 1;
				if (! checkdate( $month, $day , $year) )
				{
				  $errmsg = sprintf($_datefield_msg['datefield_not_effective_date'], $chkedStr );
				  return $errmsg;
				}
		}
	}
	return "";
}

function plugin_datefield_outputErrMsg($page, $errmsg)
{
	global $_title_cannotedit;

	$body = $title =
	  str_replace('$1',htmlspecialchars(strip_bracket($page)),$_title_cannotedit);
	$body .= "<br />datefield.inc.php : <br />" .$errmsg;
	
	$page = str_replace('$1',make_search($page),$_title_cannotedit);
	catbody($title,$page,$body);
	exit;
}

function plugin_datefield_getDate($dateStr, $formatStr)
{
	$formatPtn = $dateArgs = preg_replace('/\//','\\/',$formatStr);
	$year = $month = $day = -1;

	$formatPtn = preg_replace('/YYYY/i','%04d',$formatPtn);
	$formatPtn = preg_replace('/(YY|MM|DD)/i','%02d',$formatPtn);

	$dateArgs =  preg_replace('/YYYY|YY/i',',\$year',$dateArgs);
	$dateArgs =  preg_replace('/MM/i',',\$month',$dateArgs);
	$dateArgs =  preg_replace('/DD/i',',\$day',$dateArgs);
	$dateArgs =  preg_replace('/[^(?!:,\$year|,\$month|,\$day)]+/','',$dateArgs);

	// ���ڤ�ʸ���� '/'(�Хå�����å���)�ξ��ϥ���������ʸ������Ϳ����
	$scanStr = preg_replace('/\//','\\/',$dateStr);

	if(! strcmp($scanStr,$formatPtn) == 0)
	{
		$formatPtn = ",\"" . $formatPtn . "\"";
		$parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
		eval($parseStr);
	}
	if( $year < 100 && $year > 0) $year += 2000;
	$date = array(
				  "year"      => $year,
				  "month"     => $month,
				  "day"       => $day,
				  "formatPtn" => $formatPtn,
				  "dateArgs"  => $dateArgs,
				  "parseStr"  => $parseStr );
	
	return $date;
}

// header�������ǰʲ��Σ��Ĥ������Ԥ�
// ��Javascipt���Ѥ��뤳�ȡ�
// ��XHTML1.0 Transitional Mode�Ǥ�ư���<form>������name°�����Ѥ����
function plugin_datefield_headDeclaration()
{
	global $pkwk_dtd, $javascript;

	// Javascipt���Ѥ��뤳�ȡ�<form>������name°�����Ѥ��뤳�Ȥ����Τ���
	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		// XHTML 1.0 Transitional
		if (! isset($pkwk_dtd) || $pkwk_dtd == PKWK_DTD_XHTML_1_1)
		{
			$pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
		}
    
		// <head> ������ؤ� <meta>������ɲ�
		$javascript = TRUE;
	}

	// <head> ������ؤ� <meta>������ɲ�
	$meta_str =
		" <meta http-equiv=\"content-script-type\" content=\"text/javascript\" /> ";
	if(! in_array($meta_str, $head_tags) )
	{
		$head_tags[] = $meta_str;
	}

}

function plugin_datefield_convert()
{
	// Javascipt���Ѥ��뤳�ȡ�<form>������name°�����Ѥ��뤳�Ȥ����Τ���
	plugin_datefield_headDeclaration();
  
	// datefield �ץ饰�������ʬ��HTML����
	$number = plugin_datefield_getNumber();
	if(func_num_args() > 0) 
    {
		$options = func_get_args();
		$value      = array_shift($options);
		$format_opt = array_shift($options);
		$caldsp_opt = array_shift($options);
		
		return plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt);
    }
	return FALSE;
}

function plugin_datefield_getNumber()
{
	global $vars;
	static $numbers = array();
	if ( ! array_key_exists($vars['page'],$numbers) )
    {
		$numbers[$vars['page']] = 0;
    }
	return $numbers[$vars['page']]++;
}

function plugin_datefield_formFormat($format_opt)
{
	$format_str= trim($format_opt);
	if( strlen($format_str) == 0 )  $format_str = 'YYYY/MM/DD';
	if( preg_match('/^[\'\"].*[\"\']$/',$format_str) )
	{ 
		$format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
	}
	else
	{
		$format_str = '\'' . $format_str . '\'';
	}
	return $format_str;
}

function plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt = '')
{
	global $script, $vars;
	global $_datefield_cfg;

	$page_enc = htmlspecialchars($vars['page']);
	$script_enc = htmlspecialchars($script);
	
	// datefield �Ѥ�<script>����������
	$extrascript = (PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE && $number == 0) ? plugin_datefield_getScript() : '';

	// ���ս񼰻���ʸ������Ф������
	$format_opt= plugin_datefield_formFormat($format_opt);
	
	// ��������ɽ��������Ф������
	if($caldsp_opt != 'CUR') $caldsp_opt = 'REL';

	// ��¸���줿���դ����ͤ����
	$formatStr =substr($format_opt,1,strlen($format_opt)-2);
	$errmsg = plugin_datefield_chkFormat($value,$formatStr);
	if( strlen($errmsg) == 0 and $caldsp_opt == 'REL')
	{
		$date= plugin_datefield_getDate($value, $formatStr);
		/* ���꤬�ʤ����� ��֤��� */
		if($date['year'] == -1) $date['year'] = date("Y",time());
		if($date['day']  == -1) $date['day']  = 1;
	}
	else
	{
		$date = array(
					  "year"  => date("Y",time()),
					  "month" => date("m",time()),
					  "day"   => date("d",time()) ); 
	}

	$field_size = strlen($format_opt); 
  
	$imagePath = IMAGE_DIR;
	$imgEdit   = $_datefield_cfg[editImage];
	$imgRefer  = $_datefield_cfg[referImage];

	$body = <<<EOD
	  <input type="text" name="infield" value="$value" size="{$field_size}" onchange="this.form.submit();" />
EOD;

	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		$body .= <<< EOD
		<input type="checkbox" name="calendar" value="null" checked=false
			onclick="_plugin_datefield_onclickClndrModal(this.form, event, $format_opt, {$date['year']},{$date['month']},{$date['day']});" />
EOD;
	}

	$body .= <<<EOD
		<input type="hidden" name="refer" value="$page_enc" />
		<input type="hidden" name="plugin" value="datefield" />
		<input type="hidden" name="number" value="$number" />
EOD;

	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		$body .= <<< EOD
		<img name="editTrigger" src="$imagePath$imgEdit" alt="edit/refer"
			onclick="_plugin_datefield_changeMode( document.datefield$number, '$imgEdit', '$imgRefer', '$imagePath');" />
EOD;
	}

	if( DATEFIELD_JUMP_TO_MODIFIED_PLACE )
	{
		$body = <<< EOD
		<a id="datefield_no_$number">
		$body
		</a>
EOD;
	}

	$body = <<< EOD
		<form name="datefield$number" action="$script_enc" method='post' style="margin:0;">
		<div style="white-space:nowrap; ">
		$body
		</div>
		</form>
EOD;

	return $extrascript . $body;
}

function plugin_datefield_getScript()
{
	global $script, $vars;
	$page_enc = htmlspecialchars($vars['page']);
	$script_enc = htmlspecialchars($script);
	$js = '<script type="text/javascript" src="'. SKIN_DIR . 'datefield.js" ></script>';
	return $js;
}
?>
