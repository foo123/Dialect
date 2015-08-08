Dialect
=======

**Cross-Platform SQL Builder for PHP, Python, Node/JS**


**Requirements:**

* Support multiple DB vendors (eg. `MySQL`, `Postgre`, `Oracle`, `SQL Server` )
* Easily extended to new DBs ( prefereably through a config setting )
* Flexible and Intuitive API
* Light-weight ( one class/file per implementation if possible )
* Speed


**see also:**  

* [Contemplate](https://github.com/foo123/Contemplate) a light-weight template engine for Node/JS, PHP, Python, ActionScript
* [HtmlWidget](https://github.com/foo123/HtmlWidget) html widgets used as (template) plugins and/or standalone for PHP, Node/JS, Python (can be used as plugins for Contemplate engine as well)
* [ModelView](https://github.com/foo123/modelview.js) a light-weight and flexible MVVM framework for JavaScript/HTML5
* [ModelView MVC jQueryUI Widgets](https://github.com/foo123/modelview-widgets) plug-n-play, state-full, full-MVC widgets for jQueryUI using modelview.js (e.g calendars, datepickers, colorpickers, tables/grids, etc..) (in progress)
* [Dromeo](https://github.com/foo123/Dromeo) a flexible, agnostic router for Node/JS, PHP, Python, ActionScript
* [PublishSubscribe](https://github.com/foo123/PublishSubscribe) a simple and flexible publish-subscribe pattern implementation for Node/JS, PHP, Python, ActionScript
* [Regex Analyzer/Composer](https://github.com/foo123/RegexAnalyzer) Regular Expression Analyzer and Composer for Node/JS, PHP, Python, ActionScript
* [Xpresion](https://github.com/foo123/Xpresion) a simple and flexible eXpression parser engine (with custom functions and variables support) for PHP, Python, Node/JS, ActionScript
* [Asynchronous](https://github.com/foo123/asynchronous.js) a simple manager for async, linearised, parallelised, interleaved and sequential tasks for JavaScript


**DB vendor sql support**

1. `MySQL`
2. `Postgre`


**Methods:**

```javascript

// -- instance methods --
// --------------------------------------------------------

var dialect = new Dialect( [String vendor="mysql"] );

// NOTE1: all methods are chainable
// NOTE2: sql fields are automaticaly escaped appropriately except if set otherwise
// NOTE3: field values are automaticaly escaped appropriately except if set otherwise

// initiate SELECT directive (resets the instance state to SELECT)
dialect.select( String | Array fields='*' );

// initiate INSERT directive (resets the instance state to INSERT)
dialect.insert( String | Array tables, String | Array fields );

// initiate UPDATE directive (resets the instance state to UPDATE)
dialect.update( String | Array tables );

// initiate DELETE directive (resets the instance state to DELETE)
dialect.del( );

// FROM directive
dialect.from( String | Array tables );

// WHERE directive
dialect.where( String | Object conditions [, String type="AND"] );

// HAVING directive
dialect.having( String | Object conditions [, String type="AND"] );

// VALUES directive
dialect.values( Array values );

// SET directive
dialect.set( Object fields_values );

// JOIN directive
dialect.join( String table, String | Object condition [, String type=""] );

// GROUP directive
dialect.group( String field [, String dir="ASC"] );

// ORDER directive
dialect.order( String field [, String dir="ASC"] );

// LIMIT directive
dialect.limit( Number count [, Number offset=0] );

// PAGE directive (an alias of LIMIT)
dialect.page( Number page, Number perpage );

// get final sql code as string (resets the instance state after this)
// dialect.toString( ) will do same
var sql_code = dialect.sql( );

// set the escaper callback that escapes strings based on actual DB charsets etc..
// else a default escaper will be used, which may not be optimal based on actual DB charset and so on..
dialect.escape( Function db_escaper );

// prepare a sql_code_string with passed parameters
var prepared_sql = dialect.prepare( String sql_code, Object parameters [, String left_delimiter='%', String right_delimiter='%'] );

// example, will automaticaly typecast the key to integer (i.e "d:" modifier)
var prepared = dialect.prepare("SELECT * FROM `table` WHERE `field` = %d:key%", {key:'12'} );

// available optional modifiers:
// NOTE: any quotes will be added automaticaly, 
// quotes, for example for parameters representing strings should not be added manualy
// r:       raw, pass as is
// l:       typecast to string suitable for a "LIKE" argument with appropriate quotes
// f:       typecast to escaped string or comma-separated list of escaped strings representing table or field reference(s) with appropriate quotes
// d:       typecast to integer or comma-separated list of integers
// s:       typecast to escaped string or comma-separated list of escaped strings with appropriate quotes (see `.escape` method above)
// if no modifier is present default typecasting is "s:" modifier, i.e as escaped and quoted string




//
// EXPERIMENTAL FEATURE: 
// Create custom "soft" views and treat as usual tables

// define/create a custom soft view
dialect
    .select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .from('t')
    .join('t2',{'t.id':'t2.id'},'inner')
    .make_view('my_view') // automaticaly clears instance state after view created, so new statements can be used
;

// use it in a SELECT statement
var query_soft_view = dialect
                        .select()
                        .from('my_view')
                        .where({f1:'2'})
                        .sql()
                    ;

// drop the custom view, if exists
dialect.clear_view('my_view');



//
// EXPERIMENTAL FEATURE: 
// Create prepared sql queries as pre-compiled templates (parses sql only once on template creation)

// define/create a prepared sql query template
dialect
    .select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .from('t')
    .where({
        f1:{eq:'%d:id%',type:'raw'} // NOTE: parameter type format is same as that used in .prepare method above
    })
    .prepare_tpl('prepared_query') // automaticaly clears instance state after tpl created, so new statements can be used
;

// or using a ready-made query string also works
dialect.prepare_tpl('prepared_query', "SELECT * FROM `table` WHERE `field` = %d:id%");

// use it
// will automaticaly typecast the key to integer (i.e "d:" modifier was used in prepared template definition)
var query_prepared = dialect.prepared('prepared_query',{id:'12'});

// drop the custom prepared sql template, if exists
dialect.clear_tpl('prepared_query');
```

**TODO**

* add full support for custom soft views [DONE PARTIALY]
* add full support for sql directives (e.g `create` / `alter` etc.. ) [DONE PARTIALY]
* add full support for other sql vendors (e.g `SQL Server` etc.. ) [DONE PARTIALY]
