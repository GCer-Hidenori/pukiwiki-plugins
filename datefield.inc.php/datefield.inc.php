<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: datefield.inc.php,v 0.5 2004/01/05 01:53:32 jjyun Exp $
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
	    plugin_datefield_chkFormat($post['refer'],$post['infield'],$para_array[1]);
	    
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


function plugin_datefield_chkFormat($page, $chkedStr, $formatStr){

  if( strlen($formatStr) == 0) $formatStr='YYYY/MM/DD';
  $formatReg = $formatStr;

  /* ��������ʸ�� ��¸�߳�ǧ */
  if(preg_match('/^.*[\'\"].*$/',$formatReg) ){ /* match character..." ' */ 
    $errmsg =
      "���ս�ʸ���� " . $formatStr .
      " �˥�������ʸ��(&nbsp;&#039;&nbsp;&quot;&nbsp;)����Ѥ��ʤ��Ǥ���������";
    plugin_datefield_outputErrMsg($page, $errmsg);
  }

  /* �����ͤ����ս񼰤Ȥ���� */
  $formatReg = preg_replace('/\//','\\/',$formatReg);
  $formatReg = '/^' . preg_replace('/[YMD]/i','\\d',$formatReg) .'$/';
  if( ! preg_match($formatReg,$chkedStr) ){
    $errmsg =
      "�����ͤ����ս� " . $formatStr . 
      " �ȹ��פ��ޤ���<br />����ѥǥ��󥰤��θ���Ƥ���������";
    plugin_datefield_outputErrMsg($page, $errmsg);
  }

  /* �������դ���������ǧ */
  $formatPtn = $dateArgs = preg_replace('/\//','\\/',$formatStr);
  $year = $month = $day = -1;
  $formatPtn = preg_replace('/YYYY/i','%04d',$formatPtn);
  $formatPtn = preg_replace('/(YY|MM|DD)/i','%02d',$formatPtn);

  $dateArgs =  preg_replace('/YYYY|YY/i',',\$year',$dateArgs);
  $dateArgs =  preg_replace('/MM/i',',\$month',$dateArgs);
  $dateArgs =  preg_replace('/DD/i',',\$day',$dateArgs);
  $dateArgs =  preg_replace('/[^(?!:,\$year|,\$month|,\$day)]+/','',$dateArgs);

  $scanStr = preg_replace('/\//','\\/',$chkedStr);
  if( strcmp($scanStr,$formatPtn) == 0){
    return TRUE;
  }
  $formatPtn = ",\"" . $formatPtn . "\"";
  $parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
  eval($parseStr);

  if($month == -1 or $day == -1){
    if( $year == -1 and $month == -1 and $day == -1){
      $errmsg =  "���ճ�ǧ�������곰���顼�Ǥ���<br />";
      $errmsg .= "��ǧ�о�ʸ����: $chkedStr <br />";
      $errmsg .= "���ս�ʸ����: $formatStr <br />";
      $errmsg .= "�����ѿ�ʸ����: $dataArgs <br />";
      $errmsg .= "�ѡ����񼰾���: $parseStr <br />";
      $errmsg .= "�ɤ߼�����:year = $year, month = $month, day = $day<br />";
      plugin_datefield_outputErrMsg($page, $errmsg);
    }
    /* �����ϰϤ���������ǧ�Τ� */
    if( $month == -1 and $day > 31 ){
	$errmsg = "���դλ��� " . $chkedStr 
	  . " ���̾��������ͤ��鳰��Ƥ��ޤ���";
	plugin_datefield_outputErrMsg($page, $errmsg);
    }else if($month == 0 or $month > 12 and $day == -1){
	$errmsg = "��λ��� " . $chkedStr 
	  . " ���̾��������ͤ��鳰��Ƥ��ޤ���";
	plugin_datefield_outputErrMsg($page, $errmsg);
    }
  }else{
    if($year == -1) $year = date("Y",time());
    if (! checkdate( $month, $day , $year) ){
      $errmsg = "�������� " . $chkedStr . " ����Ŭ�ڤǤ���";
      plugin_datefield_outputErrMsg($page, $errmsg);
    }
  }
  return TRUE;
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



function plugin_datefield_convert() {
  $number = plugin_datefield_getNumber();
  if(func_num_args() > 0) 
    {
      $options = func_get_args();
      $value = array_shift($options);
      $option = array_shift($options);
      return plugin_datefield_getBody($number, $value, $option);
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


function plugin_datefield_getBody($number, $value, $option) {
  global $script, $vars;
  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);
  $body = ($number == 0) ? plugin_datefield_getScript() : '';
  $option= trim($option);
  if(strlen($option) == 0 )  $option = 'YYYY/MM/DD';
  if(preg_match('/^[\'\"].*[\"\']$/',$option)){ /* " */
    $option = '\'' . substr($option,1,strlen($option)-2) . '\'';
  }else{
    $option = '\'' . $option . '\'';
  }
  
  $field_size = strlen($option); 

  $body .= <<<EOD
    <form name="subClndr$number" action="$script_enc"
    method='post' style="margin:0;">
    <div  style="white-space:nowrap; ">
    <input type="text" name="infield" value="$value" size="{$field_size}"
    onchange="this.form.submit();" />
    <input type="button" value="��"
    onclick="dspCalendar(this.form.infield, event, $option );" />
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



