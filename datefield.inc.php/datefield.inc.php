<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: datefield.inc.php,v 0.7 2004/01/25 19:02:32 jjyun Exp $
//

/* [��ά������]
 * ����������������դ��ե�������󶡥ץ饰����
 *
 * �������Ϥ�Ԥ碌�����ƥ����ȥե�����ɤȡ�
 * �������Ϥ�Ԥ�����Υ���������ɽ������ܥ�����󶡤��ޤ���
 * ���������ˤ���������Ϥˤ��,�����ڡ����ι������Ԥ��ޤ���
 * ���������ؤΰ����ˤϡ��ƥ����ȥե�����ɤؤ�������
 * ���ս�(�ǥե������(�֥�󥯲�ǽ),[���ս�����])
 * 
 * ���¡�JavaScript ��ȤäƤ���Τ���,
 * Javascript�����ѤǤ���Ķ��Ǥʤ����ư���ޤ���
 */ 

function plugin_datefield_action() {
  global $vars, $post;
  global $html_transitional;
  check_editable($post['refer'], true, true);

  $number = 0;
  $pagedata = '';
  $pagedata_old  = get_source($post['refer']);

  foreach($pagedata_old as $line) {
    if (!preg_match('/^(?:\/\/| )/', $line)) {
      if (preg_match_all('/(?:#datefield\(([^\)]*)\))/', $line,
  			 $matches, PREG_SET_ORDER)) {
	$paddata = preg_split('/#datefield\([^\)]*\)/', $line);
  	$line = $paddata[0];
	
  	foreach($matches as $i => $match) {
  	  $opt = $match[1];
  	  if ($post['number'] == $number++) {
  	    //�������åȤΥץ饰������ʬ
	    $para_array=preg_split('/,/',$opt);
	    $errmsg = plugin_datefield_chkFormat($post['infield'],$para_array[1]);
	    if(strlen($errmsg)>0){
		plugin_datefield_outputErrMsg($post['refer'], $errmsg);
	    }	    
	    
  	    $opt = preg_replace('/[^,]*/', $post['infield'], $opt, 1);
  	  }
  	  $line .= "#datefield($opt)" . $paddata[$i+1];
  	}
      }
    }
    $pagedata .= $line;
  }

  page_write($post['refer'], $pagedata); 
  return array('msg' => '', 'body' => '');
}

/* * function plugin_datefield_chkFormat($chkedStr, $formatStr) 
 * ����(��ǧ�о�)ʸ��������ս�ʸ����γ�ǧ��Ԥ�
 * ���꤬�ʤ���ж�ʸ������Զ�礬����Ф������Ƥ򼨤�ʸ������֤�
 */
function plugin_datefield_chkFormat($chkedStr, $formatStr){
  if( strlen($formatStr) == 0) $formatStr='YYYY/MM/DD';
  $formatReg = $formatStr;

  /* ��������ʸ�� ��¸�߳�ǧ */
  if(preg_match('/^.*[\'\"].*$/',$formatReg) ){ /* match character..." ' */ 
    $errmsg =
      "���ս�ʸ���� " . $formatStr .
      " �˥�������ʸ��(&nbsp;&#039;&nbsp;&quot;&nbsp;)����Ѥ��ʤ��Ǥ���������";
    return $errmsg;
  }

  /* �����ͤ����ս񼰤Ȥ���� */
  $formatReg = preg_replace('/\//','\\/',$formatReg);
  $formatReg = '/^' . preg_replace('/[YMD]/i','\\d',$formatReg) .'$/';
  if( ! preg_match($formatReg,$chkedStr) ){
    $errmsg =
      "�����ͤ����ս� " . $formatStr . 
      " �ȹ��פ��ޤ���<br />����ѥǥ��󥰤��θ���Ƥ���������";
    return $errmsg;
  }

  $date = plugin_datefield_getDate($chkedStr, $formatStr);
  $year  = $date['year'];
  $month = $date['month'];
  $day   = $date['day'];

  if( $year == -1 and $month == -1 and $day == -1){
    $errmsg =  "���ճ�ǧ�������곰���顼�Ǥ���<br />";
    $errmsg .= "��ǧ�о�ʸ����: $chkedStr <br />";
    $errmsg .= "���ս�ʸ����: $formatStr <br />";
    $errmsg .= "�����ѿ�ʸ����: {$date['dateArgs']} <br />";
    $errmsg .= "�ѡ����񼰾���: {$date['parseStr']} <br />";
    $errmsg .= "�ɤ߼�����:year = $year, month = $month, day = $day<br />";
    return $errmsg;
  }else if($month <= 0 or $month > 12){
    /* ��λ����ɬ�� */
    $errmsg = "��λ��� " . $chkedStr    . " ���̾��������ͤ��鳰��Ƥ��ޤ���";
    return $errmsg;
  }else{
    /* ����꤬������� */
    if( $day > 31){
      $errmsg = "���դλ��� " . $chkedStr  . " ���̾��������ͤ��鳰��Ƥ��ޤ���";
      return $errmsg;
    }else{
      /* ���꤬�ʤ����� ��֤��� */
      if($year == -1) $year = date("Y",time());
      if($day  == -1) $day  = 1;
      if (! checkdate( $month, $day , $year) ){
        $errmsg = "�������� " . $chkedStr . " ����Ŭ�ڤǤ���";
	return $errmsg;
      }
    }
  }
  return "";
}
  
function plugin_datefield_outputErrMsg($page, $errmsg){
  global $_title_cannotedit;

  $body = $title =
  str_replace('$1',htmlspecialchars(strip_bracket($page)),$_title_cannotedit);
  $body .= "<br />datefield.inc.php : <br />" .$errmsg;
  
  $page = str_replace('$1',make_search($page),$_title_cannotedit);
  catbody($title,$page,$body);
  exit;
}  

function plugin_datefield_getDate($dateStr, $formatStr){
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

  if(! strcmp($scanStr,$formatPtn) == 0){
    $formatPtn = ",\"" . $formatPtn . "\"";
    $parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
    eval($parseStr);
  }
  $date = array(
    "year"      => $year,
    "month"     => $month,
    "day"       => $day,
    "formatPtn" => $formatPtn,
    "dateArgs"  => $dateArgs,
    "parseStr"  => $parseStr );

  return $date;
}


function plugin_datefield_convert() {
  global $html_transitional, $head_tags;

  // XHTML 1.0 Transitional
  $html_transitional = TRUE;

  // <head> ������ؤ� <meta>������ɲ�
  $meta_str =
   " <meta http-equiv=\"content-script-type\" content=\"text/javascript\" /> ";
  if(! in_array($meta_str, $head_tags) ){
    $head_tags[] = $meta_str;
  }

  // datefield �ץ饰�������ʬ��HTML����
  $number = plugin_datefield_getNumber();
  if(func_num_args() > 0) 
    {
      $options = func_get_args();
      $value      = array_shift($options);
      $format_opt = array_shift($options);
      $caldsp_opt = array_shift($options);
      
      return plugin_datefield_getBody(
	     $number, $value, $format_opt, $caldsp_opt);
    }
  return FALSE;
}

function plugin_datefield_getNumber() {
  global $vars;
  static $numbers = array();
  if (!array_key_exists($vars['page'],$numbers))
    {
      $numbers[$vars['page']] = 0;
    }
  return $numbers[$vars['page']]++;
}


function plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt) {
  global $script, $vars;

  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);

  // datefield �Ѥ�<script>����������
  $body = ($number == 0) ? plugin_datefield_getScript() : '';

  // ���ս񼰻���ʸ������Ф������
  $format_opt= trim($format_opt);
  if(strlen($format_opt) == 0 )  $format_opt = 'YYYY/MM/DD';
  if(preg_match('/^[\'\"].*[\"\']$/',$format_opt)){ /* " */
    $format_opt = '\'' . substr($format_opt,1,strlen($format_opt)-2) . '\'';
  }else{
    $format_opt = '\'' . $format_opt . '\'';
  }
  
  // ��������ɽ��������Ф������
  if($caldsp_opt != 'CUR') $caldsp_opt = 'REL';

  // ��¸���줿���դ����ͤ����
  $formatStr =substr($format_opt,1,strlen($format_opt)-2);
  $errmsg = plugin_datefield_chkFormat($value,$formatStr);
  if(strlen($errmsg)==0 and $caldsp_opt == 'REL'){
    $date= plugin_datefield_getDate($value, $formatStr);
    /* ���꤬�ʤ����� ��֤��� */
    if($date['year'] == -1) $date['year'] = date("Y",time());
    if($date['day']  == -1) $date['day']  = 1;
  }else{
    $date = array(
     "year"  => date("Y",time()),
     "month" => date("m",time()),
     "day"   => date("d",time()) ); 
  }

  $field_size = strlen($format_opt); 

  $body .= <<<EOD
    <form name="subClndr$number" action="$script_enc"
    method='post' style="margin:0;">
    <div  style="white-space:nowrap; ">
    <input type="text" name="infield" value="$value" size="{$field_size}"
    onchange="this.form.submit();" />
    <input type="button" value="��"
    onclick="dspCalendar(this.form.infield, event, $format_opt, 0,
     {$date['year']},{$date['month']}-1,{$date['day']} );" />
      <input type="hidden" name="refer" value="$page_enc" />
      <input type="hidden" name="plugin" value="datefield" />
      <input type="hidden" name="number" value="$number" />
    </div>
    </form>
EOD;

  return $body;
}

function plugin_datefield_getScript() {
  global $script, $vars;
  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);
  $js = <<<EOD
    <script type="text/javascript" src="skin/datefield.js" ></script>
EOD;
  return $js;
}
?>
