<?php
/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
* 
*   @version: 0.8.0
*   https://github.com/foo123/Dialect
*
*   Abstract the construction of SQL queries
*   Support multiple DB vendors
*   Intuitive and Flexible API
**/

// https://github.com/foo123/StringTemplate
if ( !class_exists('StringTemplate') )
{
class StringTemplate
{    
    const VERSION = '1.0.0';
    private static $GUID = 0;
    
    private static function guid( )
    {
        return time().'--'.(++self::$GUID);
    }
    
    public static function multisplit($tpl, $reps, $as_array=false)
    {
        $a = array( array(1, $tpl) );
        foreach ((array)$reps as $r=>$s)
        {
            $c = array( ); 
            $sr = $as_array ? $s : $r;
            $s = array(0, $s);
            foreach ($a as $ai)
            {
                if (1 === $ai[ 0 ])
                {
                    $b = explode($sr, $ai[ 1 ]);
                    $bl = count($b);
                    $c[] = array(1, $b[0]);
                    if ($bl > 1)
                    {
                        for ($j=0; $j<$bl-1; $j++)
                        {
                            $c[] = $s;
                            $c[] = array(1, $b[$j+1]);
                        }
                    }
                }        
                else
                {
                    $c[] = $ai;
                }
            }
            $a = $c;
        }
        return $a;
    }

    public static function multisplit_re( $tpl, $re ) 
    {
        $a = array(); 
        $i = 0; 
        while ( preg_match($re, $tpl, $m, PREG_OFFSET_CAPTURE, $i) ) 
        {
            $a[] = array(1, substr($tpl, $i, $m[0][1]-$i));
            $a[] = array(0, isset($m[1]) ? $m[1][0] : $m[0][0]);
            $i = $m[0][1] + strlen($m[0][0]);
        }
        $a[] = array(1, substr($tpl, $i));
        return $a;
    }
    
    public static function arg($key=null, $argslen=null)
    {
        $out = '$args';
        
        if ($key)
        {
            if (is_string($key))
                $key = !empty($key) ? explode('.', $key) : array();
            else 
                $key = array($key);
            $givenArgsLen = (bool)(null !=$argslen && is_string($argslen));
            
            foreach ($key as $k)
            {
                $kn = is_string($k) ? intval($k,10) : $k;
                if (!is_nan($kn))
                {
                    if ($kn < 0) $k = ($givenArgsLen ? $argslen : 'count('.$out.')') . ('-'.(-$kn));
                    
                    $out .= '[' . $k . ']';
                }
                else
                {
                    $out .= '["' . $k . '"]';
                }
            }
        }        
        return $out;
    }

    public static function compile($tpl, $raw=false)
    {
        static $NEWLINE = '/\\n\\r|\\r\\n|\\n|\\r/'; 
        static $SQUOTE = "/'/";
        
        if (true === $raw)
        {
            $out = 'return (';
            foreach ($tpl as $tpli)
            {
                $notIsSub = $tpli[ 0 ];
                $s = $tpli[ 1 ];
                $out .= $notIsSub ? $s : self::arg($s);
            }
            $out .= ');';
        }    
        else
        {
            $out = '$argslen=count($args); return (';
            foreach ($tpl as $tpli)
            {
                $notIsSub = $tpli[ 0 ];
                $s = $tpli[ 1 ];
                if ($notIsSub) $out .= "'" . preg_replace($NEWLINE, "' + \"\\n\" + '", preg_replace($SQUOTE, "\\'", $s)) . "'";
                else $out .= " . strval(" . self::arg($s,'$argslen') . ") . ";
            }
            $out .= ');';
        }
        return create_function('$args', $out);
    }

    
    public static $defaultArgs = '/\\$(-?[0-9]+)/';
    
    public $id = null;
    public $tpl = null;
    protected $_args = null;
    protected $_parsed = false;
    private $_renderer = null;
    
    public function __construct($tpl='', $replacements=null, $compiled=false)
    {
        $this->id = null;
        $this->_renderer = null;
        $this->tpl = null;
        $this->_args = array($tpl,!$replacements||empty($replacements)?self::$defaultArgs:$replacements,$compiled);
        $this->_parsed = false;
    }

    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose()
    {
        $this->id = null;
        $this->tpl = null;
        $this->_args = null;
        $this->_parsed = null;
        $this->_renderer = null;
        return $this;
    }
    
    public function parse( )
    {
        if ( false === $this->_parsed )
        {
            // lazy init
            $tpl = $this->_args[0]; $replacements = $this->_args[1]; $compiled = $this->_args[2];
            $this->_args = null;
            $this->tpl = is_string($replacements)
                    ? self::multisplit_re( $tpl, $replacements)
                    : self::multisplit( $tpl, (array)$replacements);
            $this->_parsed = true;
            if (true === $compiled) $this->_renderer = self::compile( $this->tpl );
        }
        return $this;
    }
    
    public function render($args=null)
    {
        if (!$args) $args = array();
        
        if ( false === $this->_parsed )
        {
            // lazy init
            $this->parse( );
        }
        
        if ($this->_renderer) 
        {
            $f = $this->_renderer;
            return $f( $args );
        }
        
        $out = ''; $argslen = count($args);
        foreach($this->tpl as $t)
        {
            if ( 1 === $t[ 0 ] )
            {
                $out .= $t[ 1 ];
            }
            else
            {
                $s = $t[ 1 ];
                if ( is_int($s) && 0 > $s ) $s += $argslen;
                $out .= $args[ $s ];
            }
        }
        return $out;
    }
}    
}

// https://github.com/foo123/GrammarTemplate
if ( !class_exists('GrammarTemplate') )
{
class GrammarTemplate__StackEntry
{
    public $value = null;
    public $prev = null;
    
    public function __construct($stack=null, $value=null)
    {
        $this->prev = $stack;
        $this->value = $value;
    }
}
class GrammarTemplate__TplEntry
{
    public $node = null;
    public $prev = null;
    public $next = null;
    
    public function __construct($node=null, $tpl=null)
    {
        if ( $tpl ) $tpl->next = $this;
        $this->node = $node;
        $this->prev = $tpl;
        $this->next = null;
    }
}

class GrammarTemplate
{    
    const VERSION = '2.0.1';
    private static $GUID = 0;
    
    private static function guid( )
    {
        return time().'--'.(++self::$GUID);
    }
    
    private static function is_array( $a )
    {
        if ( (null != $a) && is_array( $a ) )
        {
            $array_keys = array_keys( $a );
            return !empty($array_keys) && (array_keys($array_keys) === $array_keys);
        }
        return false;
    }
    
    private static function walk( $obj, $keys, $keys_alt=null, $obj_alt=null )
    {
        $found = 0;
        if ( $keys )
        {
            $o = $obj;
            $l = count($keys);
            $i = 0;
            $found = 1;
            while( $i < $l )
            {
                $k = $keys[$i++];
                if ( isset($o) )
                {
                    if ( is_array($o) && isset($o[$k]) )
                    {
                        $o = $o[$k];
                    }
                    elseif ( is_object($o) && isset($o->{$k}) )
                    {
                        $o = $o->{$k};
                    }
                    else
                    {
                        $found = 0;
                        break;
                    }
                }
                else
                {
                    $found = 0;
                    break;
                }
            }
        }
        if ( !$found && $keys_alt )
        {
            $o = $obj;
            $l = count($keys_alt);
            $i = 0;
            $found = 1;
            while( $i < $l )
            {
                $k = $keys_alt[$i++];
                if ( isset($o) )
                {
                    if ( is_array($o) && isset($o[$k]) )
                    {
                        $o = $o[$k];
                    }
                    elseif ( is_object($o) && isset($o->{$k}) )
                    {
                        $o = $o->{$k};
                    }
                    else
                    {
                        $found = 0;
                        break;
                    }
                }
                else
                {
                    $found = 0;
                    break;
                }
            }
        }
        if ( !$found && (null !== $obj_alt) && ($obj_alt !== $obj) )
        {
            if ( $keys )
            {
                $o = $obj_alt;
                $l = count($keys);
                $i = 0;
                $found = 1;
                while( $i < $l )
                {
                    $k = $keys[$i++];
                    if ( isset($o) )
                    {
                        if ( is_array($o) && isset($o[$k]) )
                        {
                            $o = $o[$k];
                        }
                        elseif ( is_object($o) && isset($o->{$k}) )
                        {
                            $o = $o->{$k};
                        }
                        else
                        {
                            $found = 0;
                            break;
                        }
                    }
                    else
                    {
                        $found = 0;
                        break;
                    }
                }
            }
            if ( !$found && $keys_alt )
            {
                $o = $obj_alt;
                $l = count($keys_alt);
                $i = 0;
                $found = 1;
                while( $i < $l )
                {
                    $k = $keys_alt[$i++];
                    if ( isset($o) )
                    {
                        if ( is_array($o) && isset($o[$k]) )
                        {
                            $o = $o[$k];
                        }
                        elseif ( is_object($o) && isset($o->{$k}) )
                        {
                            $o = $o->{$k};
                        }
                        else
                        {
                            $found = 0;
                            break;
                        }
                    }
                    else
                    {
                        $found = 0;
                        break;
                    }
                }
            }
        }
        return $found ? $o : null;
    }
    
    public static function multisplit( $tpl, $delims )
    {
        $IDL = $delims[0]; $IDR = $delims[1];
        $OBL = $delims[2]; $OBR = $delims[3]; $TPL = $delims[4];
        $lenIDL = strlen($IDL); $lenIDR = strlen($IDR);
        $lenOBL = strlen($OBL); $lenOBR = strlen($OBR); $lenTPL = strlen($TPL);
        $ESC = '\\'; $OPT = '?'; $OPTR = '*'; $NEG = '!'; $DEF = '|';
        $REPL = '{'; $REPR = '}'; $DOT = '.'; $REF = ':';
        $default_value = null; $negative = 0; $optional = 0;
        $l = strlen($tpl);
        
        $a = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> ''));
        $cur_arg = (object)array(
            'type'    => 1,
            'name'    => null,
            'key'     => null,
            'stpl'    => null,
            'dval'    => null,
            'opt'     => 0,
            'neg'     => 0,
            'start'   => 0,
            'end'     => 0
        );
        $roottpl = $a; $block = null;
        $opt_args = null; $subtpl = array(); $cur_tpl = null; $arg_tpl = array(); $start_tpl = null;
        $stack = null; $s = ''; $escaped = false;
        
        $i = 0;
        while( $i < $l )
        {
            $ch = $tpl[$i];
            if ( $ESC === $ch )
            {
                $escaped = !$escaped;
                $i += 1;
            }
            
            if ( $IDL === substr($tpl,$i,$lenIDL) )
            {
                $i += $lenIDL;
                
                if ( $escaped )
                {
                    $s .= $IDL;
                    $escaped = false;
                    continue;
                }
                
                if ( strlen($s) )
                {
                    if ( 0 === $a->node->type ) $a->node->val .= $s;
                    else $a = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> $s), $a);
                }
                
                $s = '';
            }
            else if ( $IDR === substr($tpl,$i,$lenIDR) )
            {
                $i += $lenIDR;
                
                if ( $escaped )
                {
                    $s .= $IDR;
                    $escaped = false;
                    continue;
                }
                
                // argument
                $argument = $s; $s = '';
                $p = strpos($argument, $DEF);
                if ( false !== $p )
                {
                    $default_value = substr($argument, $p+1);
                    $argument = substr($argument, 0, $p);
                }
                else
                {
                    $default_value = null;
                }
                $c = $argument[0];
                if ( $OPT === $c || $OPTR === $c )
                {
                    $optional = 1;
                    if ( $OPTR === $c )
                    {
                        $start_i = 1;
                        $end_i = -1;
                    }
                    else
                    {
                        $start_i = 0;
                        $end_i = 0;
                    }
                    $argument = substr($argument,1);
                    if ( $NEG === $argument[0] )
                    {
                        $negative = 1;
                        $argument = substr($argument,1);
                    }
                    else
                    {
                        $negative = 0;
                    }
                }
                elseif ( $REPL === $c )
                {
                    $s = ''; $j = 1; $jl = strlen($argument);
                    while ( $j < $jl && $REPR !== $argument[$j] ) $s .= $argument[$j++];
                    $argument = substr($argument, $j+1);
                    $s = explode(',', $s);
                    if ( count($s) > 1 )
                    {
                        $start_i = trim($s[0]);
                        $start_i = strlen($start_i) ? intval($start_i,10) : 0;
                        if ( is_nan($start_i) ) $start_i = 0;
                        $end_i = trim($s[1]);
                        $end_i = strlen($end_i) ? intval($end_i,10) : -1;
                        if ( is_nan($end_i) ) $end_i = 0;
                        $optional = 1;
                    }
                    else
                    {
                        $start_i = trim($s[0]);
                        $start_i = strlen($start_i) ? intval($start_i,10) : 0;
                        if ( is_nan($start_i) ) $start_i = 0;
                        $end_i = $start_i;
                        $optional = 0;
                    }
                    $s = '';
                    $negative = 0;
                }
                else
                {
                    $optional = 0;
                    $negative = 0;
                    $start_i = 0;
                    $end_i = 0;
                }
                if ( $negative && (null === $default_value) ) $default_value = '';
                
                $template = false !== strpos($argument, $REF) ? explode($REF, $argument) : array($argument,null);
                $argument = $template[0]; $template = $template[1];
                $nested = false !== strpos($argument, $DOT) ? explode($DOT, $argument) : null;
                
                if ( $cur_tpl && !isset($arg_tpl[$cur_tpl]) ) $arg_tpl[$cur_tpl] = array();
                
                if ( $TPL.$OBL === substr($tpl,$i,$lenTPL+$lenOBL) )
                {
                    // template definition
                    $i += $lenTPL;
                    $template = $template&&strlen($template) ? $template : 'grtpl--'.self::guid( );
                    $start_tpl = $template;
                    if ( $cur_tpl && strlen($argument))
                        $arg_tpl[$cur_tpl][$argument] = $template;
                }
                
                if ( !strlen($argument) ) continue; // template definition only
                
                if ( (null==$template) && $cur_tpl && isset($arg_tpl[$cur_tpl]) && isset($arg_tpl[$cur_tpl][$argument]) )
                    $template = $arg_tpl[$cur_tpl][$argument];
                
                if ( $optional && !$cur_arg->opt )
                {
                    $cur_arg->name = $argument;
                    $cur_arg->key = $nested;
                    $cur_arg->stpl = $template;
                    $cur_arg->dval = $default_value;
                    $cur_arg->opt = $optional;
                    $cur_arg->neg = $negative;
                    $cur_arg->start = $start_i;
                    $cur_arg->end = $end_i;
                    // handle multiple optional arguments for same optional block
                    $opt_args = new GrammarTemplate__StackEntry(null, array($argument,$nested,$negative,$start_i,$end_i));
                }
                else if ( $optional )
                {
                    // handle multiple optional arguments for same optional block
                    $opt_args = new GrammarTemplate__StackEntry($opt_args, array($argument,$nested,$negative,$start_i,$end_i));
                }
                else if ( !$optional && (null === $cur_arg->name) )
                {
                    $cur_arg->name = $argument;
                    $cur_arg->key = $nested;
                    $cur_arg->stpl = $template;
                    $cur_arg->dval = $default_value;
                    $cur_arg->opt = 0;
                    $cur_arg->neg = $negative;
                    $cur_arg->start = $start_i;
                    $cur_arg->end = $end_i;
                    // handle multiple optional arguments for same optional block
                    $opt_args = new GrammarTemplate__StackEntry(null, array($argument,$nested,$negative,$start_i,$end_i));
                }
                $a = new GrammarTemplate__TplEntry((object)array(
                    'type'    => 1,
                    'name'    => $argument,
                    'key'     => $nested,
                    'stpl'    => $template,
                    'dval'    => $default_value,
                    'opt'     => $optional,
                    'start'   => $start_i,
                    'end'     => $end_i
                ), $a);
            }
            else if ( $OBL === substr($tpl,$i,$lenOBL) )
            {
                $i += $lenOBL;
                
                if ( $escaped )
                {
                    $s .= $OBL;
                    $escaped = false;
                    continue;
                }
                
                // optional block
                if ( strlen($s) )
                {
                    if ( 0 === $a->node->type ) $a->node->val .= $s;
                    else $a = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> $s), $a);
                }
                
                $s = '';
                $stack = new GrammarTemplate__StackEntry($stack, array($a, $block, $cur_arg, $opt_args, $cur_tpl, $start_tpl));
                if ( $start_tpl ) $cur_tpl = $start_tpl;
                $start_tpl = null;
                $cur_arg = (object)array(
                    'type'    => 1,
                    'name'    => null,
                    'key'     => null,
                    'stpl'    => null,
                    'dval'    => null,
                    'opt'     => 0,
                    'neg'     => 0,
                    'start'   => 0,
                    'end'     => 0
                );
                $opt_args = null;
                $a = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> ''));
                $block = $a;
            }
            else if ( $OBR === substr($tpl,$i,$lenOBR) )
            {
                $i += $lenOBR;
                
                if ( $escaped )
                {
                    $s .= $OBR;
                    $escaped = false;
                    continue;
                }
                
                $b = $a;
                $cur_block = $block;
                $prev_arg = $cur_arg;
                $prev_opt_args = $opt_args;
                if ( $stack )
                {
                    $a = $stack->value[0];
                    $block = $stack->value[1];
                    $cur_arg = $stack->value[2];
                    $opt_args = $stack->value[3];
                    $cur_tpl = $stack->value[4];
                    $start_tpl = $stack->value[5];
                    $stack = $stack->prev;
                }
                else
                {
                    $a = null;
                }
                if ( strlen($s) )
                {
                    if ( 0 === $b->node->type ) $b->node->val .= $s;
                    else $b = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> $s), $b);
                }
                
                $s = '';
                if ( $start_tpl )
                {
                    $subtpl[$start_tpl] = new GrammarTemplate__TplEntry((object)array(
                        'type'    => 2,
                        'name'    => $prev_arg->name,
                        'key'     => $prev_arg->key,
                        'start'   => 0,
                        'end'     => 0,
                        'opt_args'=> null,
                        'tpl'     => $cur_block
                    ));
                    $start_tpl = null;
                }
                else
                {
                    $a = new GrammarTemplate__TplEntry((object)array(
                        'type'    => -1,
                        'name'    => $prev_arg->name,
                        'key'     => $prev_arg->key,
                        'start'   => $prev_arg->start,
                        'end'     => $prev_arg->end,
                        'opt_args'=> $prev_opt_args,
                        'tpl'     => $cur_block
                    ), $a);
                }
            }
            else
            {
                if ( $ESC === $ch ) $s .= $ch;
                else $s .= $tpl[$i++];
            }
        }
        if ( strlen($s) )
        {
            if ( 0 === $a->node->type ) $a->node->val .= $s;
            else $a = new GrammarTemplate__TplEntry((object)array('type'=> 0, 'val'=> $s), $a);
        }
        return array($roottpl, &$subtpl);
    }

    public static function optional_block( $args, $block, &$SUB, $index=null, $orig_args=null )
    {
        $out = '';
        $block_arg = null;
        
        if ( -1 === $block->type )
        {
            // optional block, check if optional variables can be rendered
            $opt_vars = $block->opt_args; if ( !$opt_vars ) return '';
            while( $opt_vars )
            {
                $opt_v = $opt_vars->value;
                $opt_arg = self::walk( $args, $opt_v[1], array($opt_v[0]), $orig_args );
                if ( (null === $block_arg) && ($block->name === $opt_v[0]) ) $block_arg = $opt_arg;
                
                if ( (0 === $opt_v[2] && null === $opt_arg) || (1 === $opt_v[2] && null !== $opt_arg) )  return '';
                $opt_vars = $opt_vars->prev;
            }
        }
        else
        {
            $block_arg = self::walk( $args, $block->key, array($block->name), $orig_args );
        }
        
        $arr = self::is_array( $block_arg ); $len = $arr ? count($block_arg) : -1;
        if ( $arr && ($len > $block->start) )
        {
            for($rs=$block->start,$re=(-1===$block->end?$len-1:min($block->end,$len-1)),$ri=$rs; $ri<=$re; $ri++)
                $out .= self::main( $args, $block->tpl, $SUB, $ri, $orig_args );
        }
        else if ( !$arr && ($block->start === $block->end) )
        {
            $out = self::main( $args, $block->tpl, $SUB, null, $orig_args );
        }
        return $out;
    }
    public static function non_terminal( $args, $symbol, &$SUB, $index=null, $orig_args=null )
    {
        $out = '';
        if ( !empty($SUB) && $symbol->stpl && isset($SUB[$symbol->stpl]) )
        {
            // using sub-template
            $opt_arg = self::walk( $args, $symbol->key, array($symbol->name), $orig_args );
            
            if ( (null !== $index/* || null !== $symbol->start*/) && (0 !== $index || !$symbol->opt) && self::is_array($opt_arg) )
            {
                $opt_arg = /*null !== $index ? (*/count($opt_arg) > $index ? $opt_arg[$index] : null/*) : (count($opt_arg) > $symbol->start ? $opt_arg[$symbol->start] : null)*/;
            }
            if ( (null === $opt_arg) && (null !== $symbol->dval) )
            {
                // default value if missing
                $out = $symbol->dval;
            }
            else
            {
                // try to associate sub-template parameters to actual input arguments
                $tpl = $SUB[$symbol->stpl]->node; $tpl_args = array();
                if ( null !== $opt_arg )
                {
                    /*if ( isset($opt_arg[$tpl->name]) && !isset($opt_arg[$symbol->name]) ) $tpl_args = $opt_arg;
                    else $tpl_args[$tpl->name] = $opt_arg;*/
                    if ( self::is_array($opt_arg) ) $tpl_args[$tpl->name] = $opt_arg;
                    else $tpl_args = $opt_arg;
                }
                $out = self::optional_block( $tpl_args, $tpl, $SUB, null, null === $orig_args ? $args : $orig_args );
            }
        }
        elseif ( $symbol->opt && (null !== $symbol->dval) )
        {
            // boolean optional argument
            $out = $symbol->dval;
        }
        else
        {
            // plain symbol argument
            $opt_arg = self::walk( $args, $symbol->key, array($symbol->name), $orig_args );
            
            // default value if missing
            if ( self::is_array($opt_arg) )
            {
                $index = null !== $index ? $index : $symbol->start;
                $opt_arg = $index < count($opt_arg) ? $opt_arg[$index] : null;
            }
            $out = (null === $opt_arg) && (null !== $symbol->dval) ? $symbol->dval : strval($opt_arg);
        }
        return $out;
    }
    public static function main( $args, $tpl, &$SUB=null, $index=null, $orig_args=null )
    {
        $out = '';
        while ( $tpl )
        {
            $tt = $tpl->node->type;
            $out .= (-1 === $tt
                ? self::optional_block( $args, $tpl->node, $SUB, $index, $orig_args ) /* optional code-block */
                : (1 === $tt
                ? self::non_terminal( $args, $tpl->node, $SUB, /*0 === $index ? ($tpl->node->opt&&$tpl->node->stpl?null:$index) : */$index, $orig_args ) /* non-terminal */
                : $tpl->node->val /* terminal */
            ));
            $tpl = $tpl->next;
        }
        return $out;
    }
    
    public static $defaultDelims = array('<','>','[',']',':='/*,'?','*','!','|','{','}'*/);
    
    public $id = null;
    public $tpl = null;
    protected $_args = null;
    protected $_parsed = false;
    
    public function __construct($tpl='', $delims=null)
    {
        $this->id = null;
        $this->tpl = null;
        if ( empty($delims) ) $delims = self::$defaultDelims;
        // lazy init
        $this->_args = array($tpl, $delims);
        $this->_parsed = false;
    }

    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose()
    {
        $this->id = null;
        $this->tpl = null;
        $this->_args = null;
        $this->_parsed = null;
        return $this;
    }
    
    public function parse( )
    {
        if ( false === $this->_parsed )
        {
            // lazy init
            $this->_parsed = true;
            $this->tpl = self::multisplit( $this->_args[0], $this->_args[1] );
            $this->_args = null;
        }
        return $this;
    }
    
    public function render($args=null)
    {
        // lazy init
        if ( false === $this->_parsed ) $this->parse( );
        return self::main( null === $args ? array() : $args, $this->tpl[0], $this->tpl[1] );
    }
}    
}

if ( !class_exists('Dialect') )
{
class DialectRef
{
    public static function parse( $r, $d ) 
    {
        // should handle field formats like:
        // [ F1(..Fn( ] [[dtb.]tbl.]col [ )..) ] [ AS alias ]
        // and extract alias, dtb, tbl, col identifiers (if present)
        // and also extract F1,..,Fn function identifiers (if present)
        $r = trim( $r ); $l = strlen( $r ); $i = 0;
        $stacks = array(array()); $stack =& $stacks[0];
        $ids = array(); $funcs = array(); $keywords2 = array('AS');
        // 0 = SEP, 1 = ID, 2 = FUNC, 5 = Keyword, 10 = *, 100 = Subtree
        $s = ''; $err = null; $paren = 0; $quote = null;
        while ( $i < $l )
        {
            $ch = $r[$i++];
            
            if ( '"' === $ch || '`' === $ch || '\'' === $ch || '[' === $ch || ']' === $ch )
            {
                // sql quote
                if ( !$quote )
                {
                    if ( strlen($s) || (']' === $ch) )
                    {
                        $err = array('invalid',$i);
                        break;
                    }
                    $quote = '[' === $ch ? ']' : $ch;
                    continue;
                }
                elseif ( $quote === $ch )
                {
                    if ( strlen($s) )
                    {
                        array_unshift($stack, array(1, $s));
                        array_unshift($ids, $s);
                        $s = '';
                    }
                    else
                    {
                        $err = array('invalid',$i);
                        break;
                    }
                    $quote = null;
                    continue;
                }
                elseif ( $quote )
                {
                    $s .= $ch;
                    continue;
                }
            }
            
            if ( $quote )
            {
                // part of sql-quoted value
                $s .= $ch;
                continue;
            }
            
            if ( '*' === $ch )
            {
                // placeholder
                if ( strlen($s) )
                {
                    $err = array('invalid',$i);
                    break;
                }
                array_unshift($stack, array(10, '*'));
                array_unshift($ids, 10);
            }
            
            elseif ( '.' === $ch )
            {
                // separator
                if ( strlen($s) )
                {
                    array_unshift($stack, array(1, $s));
                    array_unshift($ids, $s);
                    $s = '';
                }
                if ( empty($stack) || 1 !== $stack[0][0] )
                {
                    // error, mismatched separator
                    $err = array('invalid',$i);
                    break;
                }
                array_unshift($stack, array(0, '.'));
                array_unshift($ids, 0);
            }
            
            elseif ( '(' === $ch )
            {
                // left paren
                $paren++;
                if ( strlen($s) )
                {
                    // identifier is function
                    array_unshift($stack, array(2, $s));
                    array_unshift($funcs, $s);
                    $s = '';
                }
                if ( empty($stack) || (2 !== $stack[0][0] && 1 !== $stack[0][0]) )
                {
                    $err = array('invalid',$i);
                    break;
                }
                if ( 1 === $stack[0][0] )
                {
                    $stack[0][0] = 2;
                    array_unshift($funcs, array_shift($ids));
                }
                array_unshift($stacks, array());
                $stack =& $stacks[0];
            }
            
            elseif ( ')' === $ch )
            {
                // right paren
                $paren--;
                if ( strlen($s) )
                {
                    $keyword = in_array(strtoupper($s), $keywords2);
                    array_unshift($stack, array($keyword ? 5 : 1, $s));
                    array_unshift($ids, $keyword ? 5 : $s);
                    $s = '';
                }
                if ( count($stacks) < 2 )
                {
                    $err = array('invalid',$i);
                    break;
                }
                // reduce
                array_unshift($stacks[1], array(100, array_shift($stacks)));
                $stack =& $stacks[0];
            }
            
            elseif ( preg_match('/\\s/u',$ch) )
            {
                // space separator
                if ( strlen($s) )
                {
                    $keyword = in_array(strtoupper($s), $keywords2);
                    array_unshift($stack, array($keyword ? 5 : 1, $s));
                    array_unshift($ids, $keyword ? 5 : $s);
                    $s = '';
                }
                continue;
            }
            
            elseif ( preg_match('/[0-9]/ui',$ch) )
            {
                if ( !strlen($s) )
                {
                    $err = array('invalid',$i);
                    break;
                }
                // identifier
                $s .= $ch;
            }
            
            elseif ( preg_match('/[a-z_]/ui',$ch) )
            {
                // identifier
                $s .= $ch;
            }
            
            else
            {
                $err = array('invalid',$i);
                break;
            }
        }
        if ( strlen($s) )
        {
            array_unshift($stack, array(1, $s));
            array_unshift($ids, $s);
            $s = '';
        }
        if ( !$err && $paren ) $err = array('paren', $l);
        if ( !$err && $quote ) $err = array('quote', $l);
        if ( !$err && 1 !== count($stacks) ) $err = array('invalid', $l);
        if ( $err )
        {
            $err_pos = $err[1]-1; $err_type = $err[0];
            if ( 'paren' == $err_type )
            {
                // error, mismatched parentheses
                throw new InvalidArgumentException('Dialect: Mismatched parentheses "'.$r.'" at position '.$err_pos.'.');
            }
            elseif ( 'quote' == $err_type )
            {
                // error, mismatched quotes
                throw new InvalidArgumentException('Dialect: Mismatched quotes "'.$r.'" at position '.$err_pos.'.');
            }
            else//if ( 'invalid' == $err_type )
            {
                // error, invalid character
                throw new InvalidArgumentException('Dialect: Invalid character "'.$r.'" at position '.$err_pos.'.');
            }
        }
        $alias = null; $alias_q = '';
        if ( (count($ids) >= 3) && (5 === $ids[1]) && (is_string($ids[0])) )
        {
            $alias = array_shift($ids);
            $alias_q = $d->quote_name( $alias );
            array_shift($ids);
        }
        $col = null; $col_q = '';
        if ( !empty($ids) && (is_string($ids[0]) || 10 === $ids[0]) )
        {
            if ( 10 === $ids[0] )
            {
                array_shift($ids);
                $col = $col_q = '*';
            }
            else
            {
                $col = array_shift($ids);
                $col_q = $d->quote_name( $col );
            }
        }
        $tbl = null; $tbl_q = '';
        if ( (count($ids) >= 2) && (0 === $ids[0]) && (is_string($ids[1])) )
        {
            array_shift($ids);
            $tbl = array_shift($ids);
            $tbl_q = $d->quote_name( $tbl );
        }
        $dtb = null; $dtb_q = '';
        if ( (count($ids) >= 2) && (0 === $ids[0]) && (is_string($ids[1])) )
        {
            array_shift($ids);
            $dtb = array_shift($ids);
            $dtb_q = $d->quote_name( $dtb );
        }
        $tbl_col = ($dtb ? $dtb.'.' : '') . ($tbl ? $tbl.'.' : '') . ($col ? $col : '');
        $tbl_col_q = ($dtb ? $dtb_q.'.' : '') . ($tbl ? $tbl_q.'.' : '') . ($col ? $col_q : '');
        return new self($col, $col_q, $tbl, $tbl_q, $dtb, $dtb_q, $alias, $alias_q, $tbl_col, $tbl_col_q, $funcs);
    }
    
    public $_func = null;
    public $_col = null;
    public $col = null;
    public $_tbl = null;
    public $tbl = null;
    public $_dtb = null;
    public $dtb = null;
    public $_alias = null;
    public $alias = null;
    public $_qualified = null;
    public $qualified = null;
    public $full = null;
    public $aliased = null;
    
    public function __construct( $_col, $col, $_tbl, $tbl, $_dtb, $dtb, $_alias, $alias, $_qual, $qual, $_func=array() ) 
    {
        $this->_col = $_col;
        $this->col = $col;
        $this->_tbl = $_tbl;
        $this->tbl = $tbl;
        $this->_dtb = $_dtb;
        $this->dtb = $dtb;
        $this->_alias = $_alias;
        $this->_qualified = $_qual;
        $this->qualified = $qual;
        $this->full = $this->qualified;
        $this->_func = (array)$_func;
        if ( !empty($this->_func) )
        {
            foreach($this->_func as $f) $this->full = $f.'('.$this->full.')';
        }
        if ( !empty($this->_alias) )
        {
            $this->alias = $alias;
            $this->aliased = $this->full . ' AS ' . $this->alias;
        }
        else
        {
            $this->alias = $this->full;
            $this->aliased = $this->full;
        }
    }
    
    public function cloned( $alias=null, $alias_q=null, $func=null )
    {
        if ( null === $alias && null === $alias_q )
        {
            $alias = $this->_alias;
            $alias_q = $this->alias;
        }
        elseif ( null !== $alias )
        {
            $alias_q = null === $alias_q ? $alias : $alias_q;
        }
        if ( null === $func )
        {
            $func = $this->_func;
        }
        return new self( $this->_col, $this->col, $this->_tbl, $this->tbl, $this->_dtb, $this->dtb, $alias, $alias_q, 
                    $this->_qualified, $this->qualified, $func );
    }
    
    public function __destruct()
    {
        $this->dispose( );
    }
    
    public function dispose( ) 
    {
        $this->_func = null;
        $this->_col = null;
        $this->col = null;
        $this->_tbl = null;
        $this->tbl = null;
        $this->_dtb = null;
        $this->dtb = null;
        $this->_alias = null;
        $this->alias = null;
        $this->_qualified = null;
        $this->qualified = null;
        $this->full = null;
        $this->aliased = null;
        return $this;
    }
}
 
class Dialect
{
    const VERSION = "0.8.0";
    //const TPL_RE = '/\\$\\(([^\\)]+)\\)/';
    
    // https://dev.mysql.com/doc/refman/5.0/en/select.html
    // https://dev.mysql.com/doc/refman/5.0/en/join.html
    // https://dev.mysql.com/doc/refman/5.5/en/expressions.html
    // https://dev.mysql.com/doc/refman/5.0/en/insert.html
    // https://dev.mysql.com/doc/refman/5.0/en/update.html
    // https://dev.mysql.com/doc/refman/5.0/en/delete.html
    // http://dev.mysql.com/doc/refman/5.7/en/create-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/drop-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/alter-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/string-functions.html
    // http://dev.mysql.com/doc/refman/5.7/en/user-variables.html
    // http://dev.mysql.com/doc/refman/5.7/en/example-user-variables.html
    // http://www.postgresql.org/docs/
    // http://www.postgresql.org/docs/9.1/static/sql-createtable.html
    // http://www.postgresql.org/docs/9.1/static/sql-droptable.html
    // http://www.postgresql.org/docs/9.1/static/sql-altertable.html
    // https://www.postgresql.org/docs/9.1/static/sql-begin.html
    // https://www.postgresql.org/docs/9.1/static/sql-start-transaction.html
    // http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
    // http://www.postgresql.org/docs/9.1/static/functions-string.html
    // https://www.postgresql.org/docs/8.3/static/plperl-global.html
    // https://msdn.microsoft.com/en-us/library/ms189499.aspx
    // https://msdn.microsoft.com/en-us/library/ms174335.aspx
    // https://msdn.microsoft.com/en-us/library/ms177523.aspx
    // https://msdn.microsoft.com/en-us/library/ms189835.aspx
    // https://msdn.microsoft.com/en-us/library/ms179859.aspx
    // https://msdn.microsoft.com/en-us/library/ms188385%28v=sql.110%29.aspx
    // https://msdn.microsoft.com/en-us/library/ms174979.aspx
    // https://msdn.microsoft.com/en-us/library/ms173790.aspx
    // https://msdn.microsoft.com/en-us/library/cc879314.aspx
    // http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
    // http://stackoverflow.com/questions/971964/limit-10-20-in-sql-server
    // https://msdn.microsoft.com/en-us/library/ms186323.aspx
    // https://www.sqlite.org/lang_createtable.html
    // https://www.sqlite.org/lang_altertable.html
    // https://www.sqlite.org/lang_select.html
    // https://www.sqlite.org/lang_insert.html
    // https://www.sqlite.org/lang_update.html
    // https://www.sqlite.org/lang_delete.html
    // https://www.sqlite.org/lang_transaction.html
    // https://www.sqlite.org/lang_expr.html
    // https://www.sqlite.org/lang_keywords.html
    // http://stackoverflow.com/questions/1824490/how-do-you-enable-limit-for-delete-in-sqlite
    // https://www.sqlite.org/lang_corefunc.html
    // http://www.codeproject.com/Questions/625472/Declare-loacl-variable-in-Sqlite-query
    public static $dialects = array(
     "mysql"            => array(
         "quotes"       => array( array("'","'","\\'","\\'"), array("`","`"), array("","") )
        
        ,"functions"    => array(
         "strpos"       => array("POSITION(",2," IN ",1,")")
        ,"strlen"       => array("LENGTH(",1,")")
        ,"strlower"     => array("LCASE(",1,")")
        ,"strupper"     => array("UCASE(",1,")")
        ,"trim"         => array("TRIM(",1,")")
        ,"quote"        => array("QUOTE(",1,")")
        ,"random"       => array("RAND()")
        ,"now"          => array("NOW()")
        )
        
        ,"clauses"      => array(
         "create"       => "CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> (\n<columns>:=[<col:COL>:=[[UNIQUE KEY <name|> <type|> (<?uniquekey>[,<*uniquekey>])][PRIMARY KEY <type|> (<?primarykey>)][[<?!index>KEY][<?index|>INDEX] <name|> <type|> (<?key>[,<*key>])][<?column> <type>[ <?!isnull><?isnotnull|>NOT NULL][ <?!isnotnull><?isnull|>NULL][ DEFAULT <?default_value>][ <?auto_increment|>AUTO_INCREMENT][ <?!primary><?unique|>UNIQUE KEY][ <?!unique><?primary|>PRIMARY KEY][ COMMENT '<?comment>'][ COLUMN_FORMAT <?format>][ STORAGE <?storage>]]][,\n<*col:COL>]]\n)[ <?options>:=[<opt:OPT>:=[[ENGINE=<?engine>][AUTO_INCREMENT=<?auto_increment>][CHARACTER SET=<?charset>][COLLATE=<?collation>]][, <*opt:OPT>]]]"
        ,"alter"        => "ALTER TABLE <alter_table>\n<columns>[<?options>]"
        ,"drop"         => "DROP TABLE[ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]"
        ,"select"       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        ,"insert"       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,"update"       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        ,"delete"       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        )
    )


    ,"postgres"         => array(
         "quotes"       => array( array("E'","'","''","''"), array("\"","\""), array("","") )
        
        ,"functions"    => array(
         "strpos"       => array("position(",2," in ",1,")")
        ,"strlen"       => array("length(",1,")")
        ,"strlower"     => array("lower(",1,")")
        ,"strupper"     => array("upper(",1,")")
        ,"trim"         => array("trim(",1,")")
        ,"quote"        => array("quote(",1,")")
        ,"random"       => array("random()")
        ,"now"          => array("now()")
        )
        
        ,"clauses"      => array(
         "create"       => "CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> (\n<columns>:=[<col:COL>:=[[<?column> <type>[ COLLATE <?collation>][ <?!isnull><?isnotnull|>NOT NULL][ <?!isnotnull><?isnull|>NULL][ DEFAULT <?default_value>][ <?!primary>UNIQUE (<?unique>[,<*unique>])][ <?!unique>PRIMARY KEY (<?primary>[,<*primary>])]]][,\n<*col:COL>]]\n)"
        ,"alter"        => "ALTER TABLE <alter_table>\n<columns>[<?options>]"
        ,"drop"         => "DROP TABLE[ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]"
        ,"select"       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,"insert"       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,"update"       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,"delete"       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        )
    )


    ,"sqlserver"        => array(
         "quotes"       => array( array("'","'","''","''"), array("[","]"), array(""," ESCAPE '\\'") )
        
        ,"functions"    => array(
         "strpos"       => array("CHARINDEX(",2,",",1,")")
        ,"strlen"       => array("LEN(",1,")")
        ,"strlower"     => array("LOWER(",1,")")
        ,"strupper"     => array("UPPER(",1,")")
        ,"trim"         => array("LTRIM(RTRIM(",1,"))")
        ,"quote"        => array("QUOTENAME(",1,",\"'\")")
        ,"random"       => array("RAND()")
        ,"now"          => array("CURRENT_TIMESTAMP")
        )
        
        ,"clauses"      => array(
         "create"       => "CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> (\n<columns>:=[<col:COL>:=[[[<?!index>KEY][<?index|>INDEX] <name|> <type|> (<?key>[,<*key>])][<?column> <type>[ <?!isnull><?isnotnull|>NOT NULL][ <?!isnotnull><?isnull|>NULL][ DEFAULT <?default_value>][ <?auto_increment|>AUTO_INCREMENT][ <?!primary><?unique|>UNIQUE KEY][ <?!unique><?primary|>PRIMARY KEY][ COMMENT '<?comment>'][ COLUMN_FORMAT <?format>][ STORAGE <?storage>]]][,\n<*col:COL>]]\n)[ <?options>:=[<opt:OPT>:=[[ENGINE=<?engine>][AUTO_INCREMENT=<?auto_increment>][CHARACTER SET=<?charset>][COLLATE=<?collation>]][, <*opt:OPT>]]]"
        ,"alter"        => "ALTER TABLE <alter_table>\n<columns>[<?options>]"
        ,"drop"         => "DROP TABLE[ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]"
        ,"select"       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>][\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]][<?!order_conditions>[\nORDER BY 1\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]]"
        ,"insert"       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,"update"       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
        ,"delete"       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
        )
    )


    ,"sqlite"           => array(
         "quotes"       => array( array("'","'","''","''"), array("\"","\""), array(""," ESCAPE '\\'") )
        
        ,"functions"    => array(
         "strpos"       => array("instr(",2,",",1,")")
        ,"strlen"       => array("length(",1,")")
        ,"strlower"     => array("lower(",1,")")
        ,"strupper"     => array("upper(",1,")")
        ,"trim"         => array("trim(",1,")")
        ,"quote"        => array("quote(",1,")")
        ,"random"       => array("random()")
        ,"now"          => array("datetime('now')")
        )
        
        ,"clauses"      => array(
         "create"       => "CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> (\n<columns>:=[<col:COL>:=[[<?column> <type|>[ <?isnotnull|>NOT NULL][ DEFAULT <?default_value>][ <?!primary><?unique|>UNIQUE KEY][ <?!unique><?primary|>PRIMARY KEY]]][,\n<*col:COL>]]\n)"
        ,"alter"        => "ALTER TABLE <alter_table>\n<columns>[<?options>]"
        ,"drop"         => "DROP TABLE[ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]"
        ,"select"       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,"insert"       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,"update"       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>]"
        ,"delete"       => "[<?!order_conditions><?!count>DELETE FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]][DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]\nORDER BY <?order_conditions> [, <*order_conditions>][\nLIMIT <?count> OFFSET <offset|0>]\n)][<?!order_conditions>DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]\nLIMIT <?count> OFFSET <offset|0>\n)]"
        )
    )
    );
    
    private $clau = null;
    private $clus = null;
    private $vews = null;
    private $tpls = null;
    private $tbls = null;
    private $cols = null;
   
    public $db = null;
    public $escdb = null;
    public $p = null;
    
    public $type = null;
    public $clauses = null;
    public $q = null;
    public $qn = null;
    public $e = null;
    
    public function __construct( $type='mysql' )
    {
        if ( empty($type) || empty(self::$dialects[ $type ]) || empty(self::$dialects[ $type ][ 'clauses' ]) )
        {
            throw new InvalidArgumentException('Dialect: SQL dialect does not exist for "'.$type.'"');
        }
        
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        $this->vews = array( );
        $this->tpls = array( );
        
        $this->db = null;
        $this->escdb = null;
        $this->p = '';
        
        $this->type = $type;
        $this->clauses =& self::$dialects[ $this->type ][ 'clauses' ];
        $this->q = self::$dialects[ $this->type ][ 'quotes' ][ 0 ];
        $this->qn = self::$dialects[ $this->type ][ 'quotes' ][ 1 ];
        $this->e = isset(self::$dialects[ $this->type ][ 'quotes' ][ 2 ]) ? self::$dialects[ $this->type ][ 'quotes' ][ 2 ] : array('','');
    }
    
    public function dispose( )
    {
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        $this->vews = null;
        $this->tpls = null;
        
        $this->db = null;
        $this->escdb = null;
        $this->p = null;
        
        $this->type = null;
        $this->clauses = null;
        $this->q = null;
        $this->qn = null;
        $this->e = null;
        
        return $this;
    }
    
    public function __destruct( )
    {
        $this->dispose( );
    }
    
	public function __toString()
	{
        $sql = $this->sql( );
        return !$sql||empty($sql) ? '' : $sql;
    }
    
    public function driver( $db=null )
    {
        if ( func_num_args() > 0 )
        {
            $this->db = $db ? $db : null;
            return $this;
        }
        return $this->db;
    }
    
    public function escape( $escdb=null )
    {
        if ( func_num_args() > 0 )
        {
            $this->escdb = $escdb && is_callable($escdb) ? $escdb : null;
            return $this;
        }
        return $this->escdb;
    }
    
    public function prefix( $prefix='' )
    {
        if ( func_num_args() > 0 )
        {
            $this->p = $prefix ? $prefix : '';
            return $this;
        }
        return $this->p;
    }
    
    public function reset( $clause )
    {
        if ( empty($clause) || !isset($this->clauses[ $clause ]) )
        {
            throw new InvalidArgumentException('Dialect: SQL clause "'.$clause.'" does not exist for dialect "'.$this->type.'"');
        }
        $this->clus = array( );
        $this->tbls = array( );
        $this->cols = array( );
        $this->clau = $clause;
        
        if ( !($this->clauses[ $this->clau ] instanceof GrammarTemplate) )
            $this->clauses[ $this->clau ] = new GrammarTemplate( $this->clauses[ $this->clau ] );
        return $this;
    }
    
    public function clear( )
    {
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        return $this;
    }
    
    public function subquery( )
    {
        $sub = new self( $this->type );
        $sub->driver( $this->driver() )->escape( $this->escape() )->prefix( $this->prefix() );
        return $sub;
    }
    
    public function sql( )
    {
        $query = null;
        if ( $this->clau && isset($this->clauses[ $this->clau ]) )
        {
            if ( isset($this->clus['select_columns']) )
                $this->clus['select_columns'] = self::map_join( $this->clus['select_columns'], 'aliased' );
            if ( isset($this->clus['from_tables']) )
                $this->clus['from_tables'] = self::map_join( $this->clus['from_tables'], 'aliased' );
            if ( isset($this->clus['insert_tables']) )
                $this->clus['insert_tables'] = self::map_join( $this->clus['insert_tables'], 'aliased' );
            if ( isset($this->clus['insert_columns']) )
                $this->clus['insert_columns'] = self::map_join( $this->clus['insert_columns'], 'full' );
            if ( isset($this->clus['update_tables']) )
                $this->clus['update_tables'] = self::map_join( $this->clus['update_tables'], 'aliased' );
            if ( isset($this->clus['create_table']) )
                $this->clus['create_table'] = self::map_join( $this->clus['create_table'], 'full' );
            if ( isset($this->clus['alter_table']) )
                $this->clus['alter_table'] = self::map_join( $this->clus['alter_table'], 'full' );
            if ( isset($this->clus['drop_tables']) )
                $this->clus['drop_tables'] = self::map_join( $this->clus['drop_tables'], 'full' );
            if ( isset($this->clus['where_conditions_required']) )
            {
                $this->clus['where_conditions'] = isset($this->clus['where_conditions']) ? ('('.$this->clus['where_conditions_required'].') AND ('.$this->clus['where_conditions'].')') : $this->clus['where_conditions_required'];
                unset($this->clus['where_conditions_required']);
            }
            if ( isset($this->clus['having_conditions_required']) )
            {
                $this->clus['having_conditions'] = isset($this->clus['having_conditions']) ? ('('.$this->clus['having_conditions_required'].') AND ('.$this->clus['having_conditions'].')') : $this->clus['having_conditions_required'];
                unset($this->clus['having_conditions_required']);
            }
            $query = $this->clauses[ $this->clau ]->render( $this->clus );
        }
        $this->clear( );
        return $query;
    }
    
    public function createView( $view ) 
    {
        if ( !empty($view) && $this->clau )
        {
            $this->vews[ $view ] = (object)array(
                'clau'=>$this->clau, 
                'clus'=>$this->clus,
                'tbls'=>$this->tbls,
                'cols'=>$this->cols
            );
            // make existing where / having conditions, required
            if ( isset($this->vews[ $view ]->clus[ 'where_conditions' ]) )
            {
                if ( !empty($this->vews[ $view ]->clus[ 'where_conditions' ]) )
                    $this->vews[ $view ]->clus[ 'where_conditions_required' ] = $this->vews[ $view ]->clus[ 'where_conditions' ];
                unset($this->vews[ $view ]->clus[ 'where_conditions' ]);
            }
            if ( isset($this->vews[ $view ]->clus[ 'having_conditions' ]) )
            {
                if ( !empty($this->vews[ $view ]->clus[ 'having_conditions' ]) )
                    $this->vews[ $view ]->clus[ 'having_conditions_required' ] = $this->vews[ $view ]->clus[ 'having_conditions' ];
                unset($this->vews[ $view ]->clus[ 'having_conditions' ]);
            }
            $this->clear( );
        }
        return $this;
    }
    
    public function useView( $view )
    {
        // using custom 'soft' view
        $selected_columns = $this->clus['select_columns'];
        
        $view = $this->vews[ $view ];
        $this->clus = self::defaults( $this->clus, $view->clus, true, true );
        $this->tbls = self::defaults( array(), $view->tbls, true );
        $this->cols = self::defaults( array(), $view->cols, true );
        
        // handle name resolution and recursive re-aliasing in views
        if ( !empty($selected_columns) )
        {
            $selected_columns = $this->refs( $selected_columns, $this->cols, true );
            $select_columns = array( );
            foreach($selected_columns as $selected_column)
            {
                if ( '*' === $selected_column->full )
                    $select_columns = array_merge($select_columns, $this->clus['select_columns']);
                else
                    $select_columns[] = $selected_column;
            }
            $this->clus['select_columns'] = $select_columns;
        }
        
        return $this;
    }
    
    public function dropView( $view ) 
    {
        if ( !empty($view) && isset($this->vews[$view]) )
        {
           unset( $this->vews[ $view ] );
        }
        return $this;
    }
    
    public function prepareTpl( $tpl /*, $query=null, $left=null, $right=null*/ ) 
    {
        if ( !empty($tpl) )
        {
            $args = func_get_args(); 
            $argslen = count($args);
            
            if ( 1 === $argslen )
            {
                $query = null;
                $left = null;
                $right = null;
                $use_internal_query = true;
            }
            elseif ( 2 === $argslen )
            {
                $query = $args[ 1 ];
                $left = null;
                $right = null;
                $use_internal_query = false;
            }
            else if ( 3 === $argslen )
            {
                $query = null;
                $left = $args[ 1 ];
                $right = $args[ 2 ];
                $use_internal_query = true;
            }
            else/* if ( 3 < argslen )*/
            {
                $query = $args[ 1 ];
                $left = $args[ 2 ];
                $right = $args[ 3 ];
                $use_internal_query = false;
            }
            
            // custom delimiters
            $left = $left ? preg_quote($left, '/') : '%';
            $right = $right ? preg_quote($right, '/') : '%';
            // custom prepared parameter format
            $pattern = '/' . $left . '(([rlfds]:)?[0-9a-zA-Z_]+)' . $right . '/';
            
            if ( $use_internal_query )
            {
                $sql = new StringTemplate( $this->sql( ), $pattern );
                //$this->clear( );
            }
            else
            {
                $sql = new StringTemplate( $query, $pattern );
            }
            
            $this->tpls[ $tpl ] = (object)array(
                'sql'=>$sql, 
                'types'=>null
            );
        }
        return $this;
    }
    
    public function prepared( $tpl, $args )
    {
        if ( !empty($tpl) && isset($this->tpls[$tpl]) )
        {
            $sql = $this->tpls[$tpl]->sql;
            $types = $this->tpls[$tpl]->types;
            if ( null === $types )
            {
                // lazy init
                $sql->parse( );
                $types = array();
                // extract parameter types
                foreach ($sql->tpl as &$tpli)
                {
                    if ( !$tpli[ 0 ] )
                    {
                        $k = explode( ':', $tpli[ 1 ] );
                        if ( isset($k[1]) )
                        {
                            $types[ $k[1] ] = $k[0];
                            $tpli[ 1 ] = $k[1];
                        }
                        else
                        {
                            $types[ $k[0] ] = "s";
                            $tpli[ 1 ] = $k[0];
                        }
                    }
                }
                $this->tpls[$tpl]->types = $types;
            }
            
            $params = array( );
            foreach((array)$args as $k=>$v)
            {
                $type = isset($types[$k]) ? $types[$k] : "s";
                switch($type)
                {
                    case 'r': 
                        // raw param
                        if ( is_array($v) )
                        {
                            $params[$k] = implode(',', $v);
                        }
                        else
                        {
                            $params[$k] = $v;
                        }
                        break;
                    
                    case 'l': 
                        // like param
                        $params[$k] = $this->like( $v ); 
                        break;
                    
                    case 'f': 
                        if ( is_array($v) )
                        {
                            // array of references, e.g fields
                            $tmp = (array)$v;
                            $params[$k] = DialectRef::parse( $tmp[0], $this )->aliased;
                            for ($i=1,$l=count($tmp); $i<$l; $i++) $params[$k] .= ','.DialectRef::parse( $tmp[$i], $this )->aliased;
                        }
                        else
                        {
                            // reference, e.g field
                            $params[$k] = DialectRef::parse( $v, $this )->aliased;
                        }
                        break;
                    
                    case 'd':
                        if ( is_array($v) )
                        {
                            // array of integers param
                            $params[$k] = implode( ',', $this->intval( (array)$v ) );
                        }
                        else
                        {
                            // integer
                            $params[$k] = $this->intval( $v );
                        }
                        break;
                    
                    case 's': 
                    default:
                        if ( is_array($v) )
                        {
                            // array of strings param
                            $params[$k] = implode( ',', $this->quote( (array)$v ) );
                        }
                        else
                        {
                            // string param
                            $params[$k] = $this->quote( $v );
                        }
                        break;
                }
            }
            return $sql->render( $params );
        }
        return '';
    }
    
    public function prepare( $query, $args=array(), $left=null, $right=null )
    {
        if ( $query && !empty($args) )
        {
            // custom delimiters
            $left = $left ? preg_quote($left, '/') : '%';
            $right = $right ? preg_quote($right, '/') : '%';
            
            // custom prepared parameter format
            $pattern = '/' . $left . '([rlfds]:)?([0-9a-zA-Z_]+)' . $right . '/';
            $prepared = '';
            while ( preg_match($pattern, $query, $m, PREG_OFFSET_CAPTURE) )
            {
                $pos = $m[0][1];
                $len = strlen($m[0][0]);
                $param = $m[2][0];
                if ( isset($args[$param]) )
                {
                    $type = isset($m[1])&&$m[1]&&strlen($m[1][0]) ? substr($m[1][0],0,-1) : "s";
                    switch($type)
                    {
                        case 'r': 
                            // raw param
                            if ( is_array($args[$param]) )
                            {
                                $param = implode(',', $args[$param]);
                            }
                            else
                            {
                                $param = $args[$param];
                            }
                            break;
                        
                        case 'l': 
                            // like param
                            $param = $this->like( $args[$param] ); 
                            break;
                        
                        case 'f': 
                            if ( is_array($args[$param]) )
                            {
                                // array of references, e.g fields
                                $tmp = (array)$args[$param];
                                $param = DialectRef::parse( $tmp[0], $this )->aliased;
                                for ($i=1,$l=count($tmp); $i<$l; $i++) $param .= ','.DialectRef::parse( $tmp[$i], $this )->aliased;
                            }
                            else
                            {
                                // reference, e.g field
                                $param = DialectRef::parse( $args[$param], $this )->aliased;
                            }
                            break;
                        
                        case 'd':
                            if ( is_array($args[$param]) )
                            {
                                // array of integers param
                                $param = implode( ',', $this->intval( (array)$args[$param] ) );
                            }
                            else
                            {
                                // integer
                                $param = $this->intval( $args[$param] );
                            }
                            break;
                        
                        case 's': 
                        default:
                            if ( is_array($args[$param]) )
                            {
                                // array of strings param
                                $param = implode( ',', $this->quote( (array)$args[$param] ) );
                            }
                            else
                            {
                                // string param
                                $param = $this->quote( $args[$param] );
                            }
                            break;
                    }
                    $prepared .= substr($query, 0, $pos) . $param;
                }
                else
                {
                    $prepared .= substr($query, 0, $pos) . $this->quote('');
                }
                $query = substr($query, $pos+$len );
            }
            if ( strlen($query) ) $prepared .= $query;
            return $prepared;
        }
        return $query;
    }
    
    public function dropTpl( $tpl ) 
    {
        if ( !empty($tpl) && isset($this->tpls[$tpl]) )
        {
           $this->tpls[ $tpl ]->sql->dispose( );
           unset( $this->tpls[ $tpl ] );
        }
        return $this;
    }
    
    public function Create( $table, $qual, $cols, $opts=null, $create_clause='create' )
    {
        if ( $this->clau !== $create_clause ) $this->reset($create_clause);
        $qual = empty($qual) ? array('ifnotexists'=>1) : $qual;
        $this->clus['create_table'] = $this->refs( $table, $this->tbls );
        $this->clus['ifnotexists'] = !empty($qual['ifnotexists']) ? 1 : null;
        $this->clus['temporary'] = !empty($qual['temporary']) ? 1 : null;
        if ( !empty($cols) )
        {
            $cols = (array)$cols;
            $this->clus['columns'] = empty($this->clus['columns']) ? $cols : array_merge($this->clus['columns'], $cols);
        }
        if ( !empty($opts) )
        {
            $opts = (array)$opts;
            $this->clus['options'] = empty($this->clus['options']) ? $opts : array_merge($this->clus['options'], $opts);
        }
        return $this;
    }
    
    public function Alter( $table, $cols, $opts=null, $alter_clause='alter' )
    {
        if ( $this->clau !== $alter_clause ) $this->reset($alter_clause);
        $this->clus['alter_table'] = $this->refs( $table, $this->tbls );
        if ( !empty($cols) )
        {
            $cols = (array)$cols;
            $this->clus['columns'] = empty($this->clus['columns']) ? $cols : array_merge($this->clus['columns'], $cols);
        }
        if ( !empty($opts) )
        {
            $opts = (array)$opts;
            $this->clus['options'] = empty($this->clus['options']) ? $opts : array_merge($this->clus['options'], $opts);
        }
        return $this;
    }
    
    public function Drop( $tables='*', $qual=null, $drop_clause='drop' )
    {
        if ( $this->clau !== $drop_clause ) $this->reset($drop_clause);
        $view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->vews[ $view ]) )
        {
            // drop custom 'soft' view
            $this->dropView( $view );
            return $this;
        }
        $tables = $this->refs( empty($tables) ? '*' : $tables, $this->tbls );
        $qual = empty($qual) ? array('ifexists'=>1) : $qual;
        $this->clus['ifexists'] = !empty($qual['ifexists']) ? 1 : null;
        if ( empty($this->clus['drop_tables']) )
            $this->clus['drop_tables'] = $tables;
        else
            $this->clus['drop_tables'] = array_merge($this->clus['drop_tables'], $tables);
        return $this;
    }
    
    public function Select( $columns='*', $select_clause='select' )
    {
        if ( $this->clau !== $select_clause ) $this->reset($select_clause);
        $columns = $this->refs( empty($columns) ? '*' : $columns, $this->cols );
        if ( empty($this->clus['select_columns']) )
            $this->clus['select_columns'] = $columns;
        else
            $this->clus['select_columns'] = array_merge($this->clus['select_columns'], $columns);
        return $this;
    }
    
    public function Insert( $tables, $columns, $insert_clause='insert' )
    {
        if ( $this->clau !== $insert_clause ) $this->reset($insert_clause);
        $view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->vews[ $view ]) && ($this->clau === $this->vews[ $view ]->clau) )
        {
            // using custom 'soft' view
            $this->useView( $view );
        }
        else
        {
            $tables = $this->refs( $tables, $this->tbls );
            $columns = $this->refs( $columns, $this->cols );
            if ( empty($this->clus['insert_tables']) )
                $this->clus['insert_tables'] = $tables;
            else
                $this->clus['insert_tables'] = array_merge($this->clus['insert_tables'], $tables);
            if ( empty($this->clus['insert_columns']) )
                $this->clus['insert_columns'] = $columns;
            else
                $this->clus['insert_columns'] = array_merge($this->clus['insert_columns'], $columns);
        }
        return $this;
    }
    
    public function Values( $values )
    {
        if ( empty($values) ) return $this;
        // array of arrays
        if ( !isset($values[0]) || !is_array($values[0]) ) $values = array($values);
        $count = count($values);
        $insert_values = array();
        for ($i=0; $i<$count; $i++)
        {
            if ( !empty($values[$i]) )
            {
                $vals = array();
                foreach ((array)$values[$i] as $val)
                {
                    if ( is_array($val) )
                    {
                        if ( isset($val['integer']) )
                        {
                            $vals[] = $this->intval( $val['integer'] );
                        }
                        elseif ( isset($val['raw']) )
                        {
                            $vals[] = $val['raw'];
                        }
                        elseif ( isset($val['string']) )
                        {
                            $vals[] = $this->quote( $val['string'] );
                        }
                    }
                    else
                    {
                        $vals[] = is_int($val) ? $val : $this->quote( $val );
                    }
                }
                $insert_values[] = '('.implode(',', $vals).')';
            }
        }
        $insert_values = implode(',', $insert_values);
        if ( !empty($this->clus['values_values']) )
            $insert_values = $this->clus['values_values'] . ',' . $insert_values;
        $this->clus['values_values'] = $insert_values;
        return $this;
    }
    
    public function Update( $tables, $update_clause='update' )
    {
        if ( $this->clau !== $update_clause ) $this->reset($update_clause);
        $view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->vews[ $view ]) && ($this->clau === $this->vews[ $view ]->clau) )
        {
            // using custom 'soft' view
            $this->useView( $view );
        }
        else
        {
            $tables = $this->refs( $tables, $this->tbls );
            if ( empty($this->clus['update_tables']) )
                $this->clus['update_tables'] = $tables;
            else
                $this->clus['update_tables'] = array_merge($this->clus['update_tables'], $tables);
        }
        return $this;
    }
    
    public function Set( $fields_values )
    {
        if ( empty($fields_values) ) return $this;
        $set_values = array();
        foreach ($fields_values as $f=>$value)
        {
            $field = $this->refs( $f, $this->cols );
            $field = $field[0]->full;
            if ( is_array($value) )
            {
                if ( isset($value['integer']) )
                {
                    $set_values[] = "$field = " . $this->intval($value['integer']);
                }
                elseif ( isset($value['raw']) )
                {
                    $set_values[] = "$field = {$value['raw']}";
                }
                elseif ( isset($value['string']) )
                {
                    $set_values[] = "$field = " . $this->quote($value['string']);
                }
                elseif ( isset($value['increment']) )
                {
                    $set_values[] = "$field = $field + " . $this->intval($value['increment']);
                }
                elseif ( isset($value['decrement']) )
                {
                    $set_values[] = "$field = $field - " . $this->intval($value['decrement']);
                }
                elseif ( isset($value['case']) )
                {
                    $set_case_value = "$field = CASE";
                    if ( isset($value['case']['when']) )
                    {
                        foreach ( $value['case']['when'] as $case_value=>$case_conditions )
                        {
                            $set_case_value .= "\nWHEN " . $this->conditions($case_conditions,false) . " THEN " . $this->quote($case_value);
                        }
                        if ( isset($value['case']['else']) )
                            $set_case_value .= "\nELSE " . $this->quote($value['case']['else']);
                    }
                    else
                    {
                        foreach ( $value['case'] as $case_value=>$case_conditions )
                        {
                            $set_case_value .= "\nWHEN " . $this->conditions($case_conditions,false) . " THEN " . $this->quote($case_value);
                        }
                    }
                    $set_case_value .= "\nEND";
                    $set_values[] = $set_case_value;
                }
            }
            else
            {
                $set_values[] = "$field = " . (is_int($value) ? $value : $this->quote($value));
            }
        }
        $set_values = implode(',', $set_values);
        if ( !empty($this->clus['set_values']) )
            $set_values = $this->clus['set_values'] . ',' . $set_values;
        $this->clus['set_values'] = $set_values;
        return $this;
    }
    
    public function Delete( $delete_clause='delete' )
    {
        if ( $this->clau !== $delete_clause ) $this->reset($delete_clause);
        return $this;
    }
    
    public function From( $tables )
    {
        if ( empty($tables) ) return $this;
        $view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->vews[ $view ]) && ($this->clau === $this->vews[ $view ]->clau) )
        {
            // using custom 'soft' view
            $this->useView( $view );
        }
        else
        {
            $tables = $this->refs( $tables, $this->tbls );
            if ( empty($this->clus['from_tables']) )
                $this->clus['from_tables'] = $tables;
            else
                $this->clus['from_tables'] = array_merge($this->clus['from_tables'], $tables);
        }
        return $this;
    }
    
    public function Join( $table, $on_cond=null, $join_type=null )
    {
        $table = $this->refs( $table, $this->tbls );
        $table = $table[0]->aliased;
        $join_type = empty($join_type) ? null : strtoupper($join_type);
        if ( empty($on_cond) )
        {
            $join_clause = array(
                'table'   => $table,
                'type'    => $join_type
            );
        }
        else
        {
            if ( is_string( $on_cond ) )
            {
                $on_cond = $this->refs( explode('=',$on_cond), $this->cols );
                $on_cond = '(' . $on_cond[0]->full . '=' . $on_cond[1]->full . ')';
            }
            else
            {
                foreach ($on_cond as $field=>$cond)
                {
                    if ( !is_array($cond) ) 
                        $on_cond[$field] = array('eq'=>$cond,'type'=>'identifier');
                }
                $on_cond = '(' . $this->conditions( $on_cond, false ) . ')';
            }
            $join_clause = array(
                'table'   => $table,
                'type'    => $join_type,
                'cond'    => $on_cond
            );
        }
        if ( empty($this->clus['join_clauses']) ) $this->clus['join_clauses'] = array($join_clause);
        else $this->clus['join_clauses'][] = $join_clause;
        return $this;
    }
    
    public function Where( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, false );
        if ( !empty($this->clus['where_conditions']) )
            $conditions = $this->clus['where_conditions'] . " ".$boolean_connective." " . $conditions;
        $this->clus['where_conditions'] = $conditions;
        return $this;
    }
    
    public function Group( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $group_condition = $column[0]->alias . " " . $dir;
        if ( !empty($this->clus['group_conditions']) )
            $group_condition = $this->clus['group_conditions'] . ',' . $group_condition;
        $this->clus['group_conditions'] = $group_condition;
        return $this;
    }
    
    public function Having( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, true );
        if ( !empty($this->clus['having_conditions']) )
            $conditions = $this->clus['having_conditions'] . " ".$boolean_connective." " . $conditions;
        $this->clus['having_conditions'] = $conditions;
        return $this;
    }
    
    public function Order( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $order_condition = $column[0]->alias . " " . $dir;
        if ( !empty($this->clus['order_conditions']) )
            $order_condition = $this->clus['order_conditions'] . ',' . $order_condition;
        $this->clus['order_conditions'] = $order_condition;
        return $this;
    }
    
    public function Limit( $count, $offset=0 )
    {
        $this->clus['count'] = intval($count,10);
        $this->clus['offset'] = intval($offset,10);
        return $this;
    }
    
    public function Page( $page, $perpage )
    {
        $page = intval($page,10); $perpage = intval($perpage,10);
        return $this->Limit( $perpage, $page*$perpage );
    }
    
    public function conditions( $conditions, $can_use_alias=false )
    {
        if ( empty($conditions) ) return '';
        if ( is_string($conditions) ) return $conditions;
        
        $condquery = '';
        $conds = array();
        $fmt = true === $can_use_alias ? 'alias' : 'full';
        
        foreach ($conditions as $f=>$value)
        {
            if ( is_array( $value ) )
            {
                if ( isset($value['raw']) )
                {
                    $conds[] = strval($value['raw']);
                    continue;
                }
                
                if ( isset($value['or']) )
                {
                    $cases = array( );
                    foreach((array)$value['or'] as $or_cl)
                    {
                        $cases[] = $this->conditions($or_cl, $can_use_alias);
                    }
                    $conds[] = implode(' OR ', $cases);
                    continue;
                }
                
                if ( isset($value['and']) )
                {
                    $cases = array( );
                    foreach((array)$value['and'] as $and_cl)
                    {
                        $cases[] = $this->conditions($and_cl, $can_use_alias);
                    }
                    $conds[] = implode(' AND ', $cases);
                    continue;
                }
                
                if ( isset($value['either']) )
                {
                    $cases = array( );
                    foreach((array)$value['either'] as $either)
                    {
                        $cases[] = $this->conditions(array("$f"=>$either), $can_use_alias);
                    }
                    $conds[] = implode(' OR ', $cases);
                    continue;
                }
                
                if ( isset($value['together']) )
                {
                    $cases = array( );
                    foreach((array)$value['together'] as $together)
                    {
                        $cases[] = $this->conditions(array("$f"=>$together), $can_use_alias);
                    }
                    $conds[] = implode(' AND ', $cases);
                    continue;
                }
                
                $field = $this->refs( $f, $this->cols );
                $field = $field[0]->{$fmt};
                $type = isset($value['type']) ? $value['type'] : 'string';
                
                if ( isset($value['case']) )
                {
                    $cases = "$field = CASE";
                    if ( isset($value['case']['when']) )
                    {
                        foreach ( $value['case']['when'] as $case_value=>$case_conditions )
                        {
                            $cases .= " WHEN " . $this->conditions($case_conditions, $can_use_alias) . " THEN " . $this->quote($case_value);
                        }
                        if ( isset($value['case']['else']) )
                            $cases .= " ELSE " . $this->quote($value['case']['else']);
                    }
                    else
                    {
                        foreach ( $value['case'] as $case_value=>$case_conditions )
                        {
                            $cases .= " WHEN " . $this->conditions($case_conditions, $can_use_alias) . " THEN " . $this->quote($case_value);
                        }
                    }
                    $cases .= " END";
                    $conds[] = $cases;
                }
                elseif ( isset($value['multi_like']) )
                {
                    $conds[] = $this->multi_like($field, $value['multi_like']);
                }
                elseif ( isset($value['like']) )
                {
                    $conds[] = "$field LIKE " . ('raw' === $type ? $value['like'] : $this->like($value['like']));
                }
                elseif ( isset($value['not_like']) )
                {
                    $conds[] = "$field NOT LIKE " . ('raw' === $type ? $value['not_like'] : $this->like($value['not_like']));
                }
                elseif ( isset($value['contains']) )
                {
                    $v = strval($value['contains']);
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $this->sql_function('strpos', array($field,$v)) . ' > 0';
                }
                elseif ( isset($value['not_contains']) )
                {
                    $v = strval($value['not_contains']);
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $this->sql_function('strpos', array($field,$v)) . ' = 0';
                }
                elseif ( isset($value['in']) )
                {
                    $v = (array) $value['in'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v[0]) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field IN (" . implode(',', $v) . ")";
                }
                elseif ( isset($value['not_in']) )
                {
                    $v = (array) $value['not_in'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v[0]) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field NOT IN (" . implode(',', $v) . ")";
                }
                elseif ( isset($value['between']) )
                {
                    $v = (array) $value['between'];
                    
                    // partial between clause
                    if ( !isset($v[0]) || (null === $v[0]) )
                    {
                        // switch to lte clause
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || is_int($v[1]) )
                        {
                            $v[1] = $this->intval( $v[1] );
                        }
                        else
                        {
                            $v[1] = $this->quote( $v[1] );
                        }
                        $conds[] = $field . " <= " . $v[1];
                    }
                    elseif ( !isset($v[1]) || (null === $v[1]) )
                    {
                        // switch to gte clause
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || is_int($v[0]) )
                        {
                            $v[0] = $this->intval( $v[0] );
                        }
                        else
                        {
                            $v[0] = $this->quote( $v[0] );
                        }
                        $conds[] = $field . " >= " . $v[0];
                    }
                    else
                    {
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || (is_int($v[0]) && is_int($v[1])) )
                        {
                            $v = $this->intval( $v );
                        }
                        else
                        {
                            $v = $this->quote( $v );
                        }
                        $conds[] = "$field BETWEEN {$v[0]} AND {$v[1]}";
                    }
                }
                elseif ( isset($value['not_between']) )
                {
                    $v = (array) $value['not_between'];
                    
                    // partial between clause
                    if ( !isset($v[0]) || (null === $v[0]) )
                    {
                        // switch to gt clause
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || is_int($v[1]) )
                        {
                            $v[1] = $this->intval( $v[1] );
                        }
                        else
                        {
                            $v[1] = $this->quote( $v[1] );
                        }
                        $conds[] = $field . " > " . $v[1];
                    }
                    elseif ( !isset($v[1]) || (null === $v[1]) )
                    {
                        // switch to lt clause
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || is_int($v[0]) )
                        {
                            $v[0] = $this->intval( $v[0] );
                        }
                        else
                        {
                            $v[0] = $this->quote( $v[0] );
                        }
                        $conds[] = $field . " < " . $v[0];
                    }
                    else
                    {
                        if ( 'raw' === $type )
                        {
                            // raw, do nothing
                        }
                        elseif ( 'integer' === $type || (is_int($v[0]) && is_int($v[1])) )
                        {
                            $v = $this->intval( $v );
                        }
                        else
                        {
                            $v = $this->quote( $v );
                        }
                        $conds[] = "$field < {$v[0]} OR $field > {$v[1]}";
                    }
                }
                elseif ( isset($value['gt']) || isset($value['gte']) )
                {
                    $op = isset($value['gt']) ? "gt" : "gte";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $field . ('gt'===$op ? " > " : " >= ") . $v;
                }
                elseif ( isset($value['lt']) || isset($value['lte']) )
                {
                    $op = isset($value['lt']) ? "lt" : "lte";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $field . ('lt'===$op ? " < " : " <= ") . $v;
                }
                elseif ( isset($value['not_equal']) || isset($value['not_eq']) )
                {
                    $op = isset($value['not_eq']) ? "not_eq" : "not_equal";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field <> $v";
                }
                elseif ( isset($value['equal']) || isset($value['eq']) )
                {
                    $op = isset($value['eq']) ? "eq" : "equal";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field = $v";
                }
            }
            else
            {
                $field = $this->refs( $f, $this->cols );
                $field = $field[0]->{$fmt};
                $conds[] = "$field = " . (is_int($value) ? $value : $this->quote($value));
            }
        }
        
        if ( !empty($conds) ) $condquery = '(' . implode(') AND (', $conds) . ')';
        return $condquery;
    }
    
    public function joinConditions( $join, &$conditions )
    {
        $j = 0;
        foreach ($conditions as $f=>$cond)
        {
            $ref = DialectRef::parse( $f, $this );
            $field = $ref->_col;
            if ( !isset($join[$field]) ) continue;
            $main_table = $join[$field]['table'];
            $main_id = $join[$field]['id'];
            $join_table = $join[$field]['join'];
            $join_id = $join[$field]['join_id'];
            
            $j++; $join_alias = "{$join_table}{$j}";
            
            $where = array( );
            if ( isset($join[$field]['key']) && $field !== $join[$field]['key'] )
            {
                $join_key = $join[$field]['key'];
                $where["{$join_alias}.{$join_key}"] = $field;
            }
            else
            {
                $join_key = $field;
            }
            if ( isset($join[$field]['value']) )
            {
                $join_value = $join[$field]['value'];
                $where["{$join_alias}.{$join_value}"] = $cond;
            }
            else
            {
                $join_value = $join_key;
                $where["{$join_alias}.{$join_value}"] = $cond;
            }
            $this->Join(
                "{$join_table} AS {$join_alias}", 
                "{$main_table}.{$main_id}={$join_alias}.{$join_id}", 
                "inner"
            )->Where( $where );
            
            unset( $conditions[$f] );
        }
        return $this;
    }
    
    public function refs( $refs, &$lookup, $re_alias=false ) 
    {
        if ( true === $re_alias )
        {
            foreach ($refs as $i=>$ref)
            {
                $alias = $ref->alias;
                $qualified = $ref->qualified;
                $qualified_full = $ref->full;
                
                if ( '*' === $qualified_full ) continue;
                
                if ( !isset($lookup[ $alias ]) )
                {
                    if ( isset($lookup[ $qualified_full ]) )
                    {
                        $ref2 = $lookup[ $qualified_full ];
                        $alias2 = $ref2->alias;
                        $qualified_full2 = $ref2->full;
                        
                        if ( ($qualified_full2 !== $qualified_full) && ($alias2 !== $alias) && ($alias2 === $qualified_full) )
                        {
                            // handle recursive aliasing
                            /*if ( ($qualified_full2 !== $alias2) && isset($lookup[ $alias2 ]) )
                                unset($lookup[ $alias2 ]);*/
                            
                            $ref2 = $ref2->cloned( $ref->alias );
                            $refs[$i] = $lookup[ $alias ] = $ref2;
                        }
                    }
                    elseif ( isset($lookup[ $qualified ]) )
                    {
                        $ref2 = $lookup[ $qualified ];
                        if ( $ref2->qualified !== $qualified ) $ref2 = $lookup[ $ref2->qualified ];
                        if ( $ref->full !== $ref->alias )
                            $ref2 = $ref2->cloned( $ref->alias, null, $ref->_func );
                        else
                            $ref2 = $ref2->cloned( null, $ref2->alias, $ref->_func );
                        $refs[$i] = $lookup[ $ref2->alias ] = $ref2;
                        if ( ($ref2->alias !== $ref2->full) && !isset($lookup[ $ref2->full ]) )
                            $lookup[ $ref2->full ] = $ref2;
                    }
                    else
                    {
                        $lookup[ $alias ] = $ref;
                        if ( ($alias !== $qualified_full) && !isset($lookup[ $qualified_full ]) )
                            $lookup[ $qualified_full ] = $ref;
                    }
                }
                else
                {
                    $refs[$i] = $lookup[ $alias ];
                }
            }
        }
        else
        {
            $rs = (array)$refs;
            $refs = array( );
            foreach ($rs as $r)
            {
                $r = explode( ',', $r );
                foreach ($r as $ref)
                {
                    $ref = DialectRef::parse( $ref, $this );
                    $alias = $ref->alias; $qualified = $ref->full;
                    if ( !isset($lookup[ $alias ]) ) 
                    {
                        $lookup[ $alias ] = $ref;
                        if ( ($qualified !== $alias) && !isset($lookup[ $qualified ]) )
                            $lookup[ $qualified ] = $ref;
                    }
                    else
                    {                    
                        $ref = $lookup[ $alias ];
                    }
                    $refs[] = $ref;
                }
            }
        }
        return $refs;
    }
    
    public function tbl( $table )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->tbl( $vi );
            return $v;
        }
        return $this->p.$table;
    }
    
    public function intval( $v )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->intval( $vi );
            return $v;
        }
        return intval( $v, 10 );
    }
    
    public function quote_name( $v, $optional=false )
    {
        $optional = true === $optional;
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->quote_name($vi, $optional);
            return $v;
        }
        elseif ( $optional )
        {
            return ($this->qn[0] == substr($v,0,strlen($this->qn[0])) ? '' : $this->qn[0]) . $v . ($this->qn[1] == substr($v,-strlen($this->qn[1])) ? '' : $this->qn[1]);
        }
        else
        {
            return $this->qn[0] . $v . $this->qn[1];
        }
    }
    
    public function quote( $v )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->quote( $vi );
            return $v;
        }
        return ($this->q[0] . $this->esc( $v ) . $this->q[1]);
    }
    
    public function like( $v )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->like( $vi );
            return $v;
        }
        $q = $this->q;
        $e = $this->escdb ? array('','') : $this->e;
        return $e[0] . $q[0] . '%' . $this->esc_like( $this->esc( $v ) ) . '%' . $q[1] . $e[1];
    }
    
    public function multi_like( $f, $v, $trimmed=true )
    {
        $trimmed = false !== $trimmed;
        $like = "$f LIKE ";
        $ORs = explode(',', $v);
        if ( $trimmed ) $ORs = array_filter(array_map('trim', $ORs), 'strlen');
        foreach($ORs as &$OR)
        {
            $ANDs = explode('+', $OR);
            if ( $trimmed ) $ANDs = array_filter(array_map('trim', $ANDs), 'strlen');
            foreach($ANDs as &$AND) $AND = $like . $this->like($AND);
            $OR = '(' . implode(' AND ', $ANDs) . ')';
        }
        return implode(' OR ', $ORs);
    }
    
    public function esc( $v )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->esc( $vi );
            return $v;
        }
        elseif ( $this->escdb )
        {
            return call_user_func( $this->escdb, $v );
        }
        else
        {
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            $chars = '\\' . chr(0); $esc = '\\';
            $q =& $this->q; $v = strval($v); $ve = '';
            for($i=0,$l=strlen($v); $i<$l; $i++)
            {
                $c = $v[$i];
                if ( $q[0] === $c ) $ve .= $q[2];
                elseif ( $q[1] === $c ) $ve .= $q[3];
                else $ve .= self::addslashes( $c, $chars, $esc );
            }
            return $ve;
        }
    }
    
    public function esc_like( $v )
    {
        if ( is_array( $v ) )
        {
            foreach($v as $i=>$vi) $v[$i] = $this->esc_like( $vi );
            return $v;
        }
        $chars = '_%'; $esc = '\\';
        return self::addslashes( $v, $chars, $esc );
    }
    
    public function sql_function( $f, $args=array() )
    {
        if ( !isset(self::$dialects[ $this->type ][ 'functions' ][ $f ]) )
            throw new InvalidArgumentException('Dialect: SQL function "'.$f.'" does not exist for dialect "'.$this->type.'"');
        $f = self::$dialects[ $this->type ][ 'functions' ][ $f ];
        $args = (array)$args;
        $argslen = count($args);
        $func = ''; $is_arg = false;
        foreach($f as $fi)
        {
            $func .= $is_arg ? (0<$fi && $argslen>=$fi ? $args[$fi-1] : '') : $fi;
            $is_arg = !$is_arg;
        }
        return $func;
    }
    
    public static function map_join( $arr, $prop, $sep=',' )
    {
        $joined = '';
        if ( !empty($arr) )
        {
            $joined = $arr[0]->{$prop};
            for($i=1,$l=count($arr); $i<$l; $i++) $joined .= $sep . $arr[$i]->{$prop};
        }
        return $joined;
    }
    
    public static function defaults( $data, $defau=array(), $overwrite=false, $array_copy=false )
    {
        $overwrite = true === $overwrite;
        $array_copy = true === $array_copy;
        foreach((array)$defau as $k=>$v)
        {
            if ( $overwrite || !isset($data[$k]) )
                $data[ $k ] = $array_copy && is_array($v) ? array_merge(array(), $v) : $v;
        }
        return $data;
    }
    
    /*public static function filter( $data, $filt, $positive=true )
    {
        if ( $positive )
        {
            $filtered = array( );
            foreach((array)$filt as $field)
            {
                if ( isset($data[$field]) ) 
                    $filtered[$field] = $data[$field];
            }
            return $filtered;
        }
        else
        {
            $filtered = array( );
            foreach($data as $field=>$v)
            {
                if ( !in_array($field, $filt) ) 
                    $filtered[$field] = $v;
            }
            return $filtered;
        }
    }*/
    
    public static function addslashes( $s, $chars=null, $esc='\\' )
    {
        $s2 = '';
        if ( null === $chars ) $chars = '\\"\'' . chr(0);
        for ($i=0,$l=strlen($s); $i<$l; $i++)
        {
            $c = $s[ $i ];
            $s2 .= false === strpos($chars, $c) ? $c : (0 === ord($c) ? '\\0' : ($esc.$c));
        }
        return $s2;
    }
}
}