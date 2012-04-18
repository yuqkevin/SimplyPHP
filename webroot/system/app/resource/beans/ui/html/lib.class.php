<?php
// -------------------------------------------------------------------------------+
// | Name: Html - W3S UI Library                                                  |
// +------------------------------------------------------------------------------+
// | Package: W3S                                                                 |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.03.30                                                         |
// -------------------------------------------------------------------------------+
//

class LibUiHtml
{
    public function hasharray_options($lines, $field_key, $field_val=null, $in=null)
    {
        $result = null;
        foreach ((array)$lines as $line) {
            $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                htmlspecialchars($line[$field_key]), strcmp($line[$field_key],$in)===0?'selected':null, htmlspecialchars($line[isset($field_val)?$field_val:$field_key]));
        }
        return $result;
    }
    public function hash_options($status_list, $status = null)
    {
        $result = null;
        foreach ($status_list as $val => $name) {
           $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                      $val, strcmp($val,$status)===0? 'selected': null, htmlspecialchars($name));
        }
        return $result; 
    }
    public function array_options($array, $in=null)
    {
        $opt = null;
        foreach ($array as $item) {
           $opt .= sprintf("<option value=\"%s\" %s>%s</option>", htmlspecialchars($item),strcmp($item,$in)===0? 'selected':null, htmlspecialchars($item));
        }
        return $opt;
    }

    public function group_options($lines, $field_key, $field_val, $field_category, $in=null)
    {
        $grp_options = null;
        $cate_flag = 0;
        foreach ($lines as $line) {
            if ($line[$field_category]) {
                if ($cate_flag) $grp_options .= "</optgroup>\n";
                $cate_flag = $line[$field_category];
                $grp_options .= sprintf("<optgroup label=\"%s\">", htmlspecialchars($line[$field_val]));
            } else {
                $grp_options .= sprintf("<option value=\"%s\" %s>%s</option>", $line[$field_key], strcmp($line[$field_key],$in)===0?'selected':null, htmlspecialchars($line[$field_category]));
            }
        }
        if ($cate_flag) $grp_options .= "</optgroup>\n";
        return $grp_options;
    }
    /** Wrap content with given wrap tag and attributes **
     * @input    String    $content
     *            String    $wrap        wrap tag  e.g <span /> <div class="12" style="color:red" /> 
     *            Array    $attrs        additional attributes for wrap tag. e.g array('class'=>'w3s-button','style'=>'border:1px solid red;')
     * @return
     *            String    New content wrapped.
    **/
    public function wrap($content, $wrap, $attrs=null)
    {
        preg_match("/<(\w+)\W/", $wrap, $p);
        $tag = $p[1];
        $wrap = substr($wrap, 0, strpos($wrap, '/>')?strpos($wrap, '/>'):-1);
        foreach ((array)$attrs as $attr=>$val) {
            if ($val) {
                 if (strpos($wrap, "$attr=\"")) {
                    $wrap = str_replace("$attr=\"","$attr=\"$val ", $wrap);
                    } else {
                    $wrap .= " $attr=\"$val\"";
                    }
            }
        }
        return "$wrap>$content</$tag>";
    }
    /*** W3S UI Widgets ***/
    public function instant_trigger($url, $param=array('class'=>null,'name'=>'popup'), $format='html')
    {
        $class = @$param['class'];
        $name = @$param['name'];
        $cmd = "$('<a />',{'href':'$url','class':'w3s-trigger w3s-hidden $class','name':'$name'}.appendTo('body').trigger('click').remove();";
        if (strtolower($format)!='html') return $cmd;
        return "<script type=\"text/javascript\">$(document).ready(function(){$cmd});</script>";
    }
    /** Trigger button generator **
     * @input    def Array array('url'=>'#','name'=>null, 'title'=>null, 'text'=>'button', 'confirm'=>null, 'token'=>null,'fields'=>null,'target'=>null)
     *            options Array ('modal'=>true,'cache'=>false,'header'=>true,'top_button'=>true)
     *             wrap    (optional), w3s-button wrap
    **/
    public function trigger ($def, $options=array(), $wrap=null)
    {
        $conf = array('id'=>'_w3s-trigger-'+Core::sequence(1),'url'=>'#','name'=>null,'text'=>'','title'=>null,'confirm'=>null,'token'=>null,'field'=>null,'target'=>null,'class'=>null);
        $attrs = array('modal'=>'w3s-modal','cache'=>'w3s-noCache','header'=>'w3s-noHeader','top_button'=>'w3s-noTopBtn','refresh'=>'noRefresh');
        $names = array(
            'popup'=>array('modal'=>true,'cache'=>false,'header'=>true,'top_button'=>true),
            'overlay'=>array('cache'=>false,'header'=>true,'top_button'=>true),
            'float'=>array('cache'=>false,'header'=>true,'top_button'=>true,'refresh'=>true),
            'other'=>array('cache'=>false)
        );
        if (!isset($def['name'])) $def['name'] = 'button';
        $category = isset($names[$def['name']])?$names[$def['name']]:$names['other'];
        foreach ($attrs as $attr=>$cls) {
            if (!isset($category[$attr])) continue;
            $val = isset($options[$attr])?$options[$attr]:$category[$attr];
            if ($val xor substr($cls,0,6)==='w3s-no') $conf['class'].=" $cls";
        }
        foreach ($conf as $k=>$v) $$k = isset($def[$k])?$def[$k]:$v;
        if ($token) $url .= ($url=='#'?null:((strpos($url,'?')!==false?'&':'?').'_token=')).urlencode($token);
        $button = "<a id=\"$id\" href=\"$url\" class=\"w3s-trigger $class\" name=\"$name\" title=\"$title\" rel=\"$confirm\" rev=\"$field\" target=\"$target\"><span>$text</span></a>";
        return $wrap?$this->wrap($button, $wrap, array('class'=>'w3s-button')):$button;
    }
    public function form_field($tag, $type, $options)
    {
        $conf = array('class'=>null,'name'=>null,'id'=>null,'value'=>null,'readonly'=>null,'disabled'=>null);
        $str = null;
        foreach ($conf as $attr=>$val) if (isset($options[$attr])) $str .= " $attr=\"{$options[$attr]}\"";
        switch (strtolower($tag)) {
            case 'input':
                return "<input type=\"$type\" $str />".@$options['note'];
                break;
        }
    }

    /** convert hasharray lines into <ul><li> tree 
     * key fields in line:
     *    title
     *    level
     *    link (optional)
     *    class (optional)
     *    desc (optional)
    **/
    public function tree($lines, $ul_wrap=true)
    {
        $list = null;
        for ($i=0; $i<count($lines); $i++) {
            $line = $lines[$i];
            if ($i) $list .= str_repeat("</ul></li>\n", max(0, intval($lines[$i-1]['level'])-$line['level']));
            $li_class = isset($line['class'])?"class=\"{$line['class']}\"":null;
            $li_inner_class = isset($line['inner_class'])?"class=\"{$line['inner_class']}\"":null;
            if (!isset($line['link'])||!$line['link']) {
                $a_attr = (isset($lines[$i+1])&&$lines[$i+1]['level']>$line['level'])?'class="w3s-title"':null;
            } elseif ($line['link'][0]==='#') {
                $p = preg_split("/,/", substr($line['link'], 1));
                if (count($p)==1) {
                    $a_attr = !$p[0]?null:"href=\"{$line['link']}\"";
                } elseif (count($p)==2) {
                    list($name, $link) = $p;
                    $a_attr = "href=\"$link\" class=\"w3s-trigger\" name=\"$name\"";
                } else {
                    $name = $p[0];
                    $target = $p[1];
                    $link = end($p);
                    $a_attr = "href=\"$link\" class=\"w3s-trigger\" name=\"$name\" target=\"$target\"";
                    
                }
            } else {
                $a_attr = "href=\"{$line['link']}\"";
            }
            $list .= sprintf("<li %s><div %s><a %s><span>%s</span></a><span>%s</span></div>",
                         $li_class, $li_inner_class, $a_attr, htmlspecialchars($line['title']), @$line['desc']);
            $list .= (isset($lines[$i+1])&&$lines[$i+1]['level']>$line['level'])?'<ul>':'</li>';
        }
        if ($i) {
            $list .= str_repeat("</ul></li>\n", max(0,$lines[$i-1]['level']-1));
            return $ul_wrap?"<ul>$list</ul>":$list;
        }
        return null;
    }
}
