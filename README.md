Dialect
=======

**Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript**

![Dialect](/dialect.jpg)

[Etymology of *"dialect"*](http://www.etymonline.com/index.php?term=dialect)


**see also:**  

* [ModelView](https://github.com/foo123/modelview.js) a light-weight and flexible MVVM framework for JavaScript/HTML5
* [ModelView MVC jQueryUI Widgets](https://github.com/foo123/modelview-widgets) plug-n-play, state-full, full-MVC widgets for jQueryUI using modelview.js (e.g calendars, datepickers, colorpickers, tables/grids, etc..) (in progress)
* [Importer](https://github.com/foo123/Importer) simple class &amp; dependency manager and loader for PHP, Node/XPCOM/JS, Python
* [HtmlWidget](https://github.com/foo123/HtmlWidget) html widgets used as (template) plugins and/or standalone for PHP, Node/XPCOM/JS, Python (can be used as plugins for Contemplate engine as well)
* [Contemplate](https://github.com/foo123/Contemplate) a light-weight template engine for Node/XPCOM/JS, PHP, Python, ActionScript
* [PublishSubscribe](https://github.com/foo123/PublishSubscribe) a simple and flexible publish-subscribe pattern implementation for Node/XPCOM/JS, PHP, Python, ActionScript
* [Dromeo](https://github.com/foo123/Dromeo) a flexible, agnostic router for Node/XPCOM/JS, PHP, Python, ActionScript
* [StringTemplate](https://github.com/foo123/StringTemplate) simple and flexible string templates for PHP, Python, Node/XPCOM/JS, ActionScript
* [GrammarTemplate](https://github.com/foo123/GrammarTemplate) versatile and intuitive grammar-based templating for PHP, Python, Node/XPCOM/JS, ActionScript
* [GrammarPattern](https://github.com/foo123/GrammarPattern) versatile grammar-based pattern-matching for Node/XPCOM/JS (IN PROGRESS)
* [Xpresion](https://github.com/foo123/Xpresion) a simple and flexible eXpression parser engine (with custom functions and variables support) for PHP, Python, Node/XPCOM/JS, ActionScript
* [Regex Analyzer/Composer](https://github.com/foo123/RegexAnalyzer) Regular Expression Analyzer and Composer for Node/XPCOM/JS, PHP, Python, ActionScript
* [RT](https://github.com/foo123/RT) client-side real-time communication for Node/XPCOM/JS with support for Poll/BOSH/WebSockets
* [Asynchronous](https://github.com/foo123/asynchronous.js) a simple manager for async, linearised, parallelised, interleaved and sequential tasks for JavaScript



###Contents

* [Requirements](#requirements)
* [DB vendor sql support](#db-vendor-sql-support)
* [Features](#features)
* [API Reference](#api-reference)
* [TODO](#todo)
* [Performance](#performance)




###Requirements

* Support multiple DB vendors (eg. `MySQL`, `PostgreSQL`, `SQLite`, `MS SQL / SQL Server`, `Oracle`, `DB2`, .. )
* Easily extended to new `DB`s ( prefereably through a, implementation-independent, config setting )
* Light-weight ( one class/file per implementation if possible )
* Speed
* Modularity and implementation-independent transferability
* Flexible and Intuitive API




###DB vendor sql support

**(partial in some cases, but easy to extend)**

1. [`MySQL`](http://dev.mysql.com/doc/refman/5.7/en/)
2. [`PostgreSQL`](http://www.postgresql.org/docs/9.1/static/reference.html)
3. [`MS SQL / Sql Server`](https://msdn.microsoft.com/en-us/library/bb510741.aspx)
4. [`SQLite`](https://www.sqlite.org/lang.html)
5. `Oracle`, .. [TODO]



###Features


**Grammar Templates**

`Dialect` (`v.0.5.0+`) uses a powerful, fast, flexible and intuitive concept: [`grammar templates`](https://github.com/foo123/GrammarTemplate), to configure an `sql` dialect, which is similar to the `SQL` (grammar) documentation format used by `SQL` providers.


`Dialect` uses a similar *grammar-like* template format, as a **description and generation** tool to produce `sql code` output relevant to a specific `sql` dialect.


For example the `SELECT` clause of `MySql` can be modeled / described as follows:

```text
SELECT <select_columns> [, <*select_columns> ]
FROM <from_tables> [, <*from_tables> ]
[ <?join_clauses> [\n <*join_clauses> ] ]
[ WHERE <?where_conditions> ]
[ GROUP BY <?group_conditions> [, <*group_conditions> ] ]
[ HAVING <?having_conditions> ]
[ ORDER BY <?order_conditions> [, <*order_conditions> ] ]
[ LIMIT <offset|0>, <?count> ]
```

The `SELECT` clause for `SQL Server (2012+)` with `LIMIT` clause emulation can be described as follows:

```text
SELECT <select_columns> [, <*select_columns> ]
FROM <from_tables> [, <*from_tables> ]
[ <?join_clauses> [\n <*join_clauses> ] ]
[ WHERE <?where_conditions> ]
[ GROUP BY <?group_conditions> [, <*group_conditions> ] ]
[ HAVING <?having_conditions> ]
[ ORDER BY <?order_conditions> [, <*order_conditions> ][ OFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY ] ]
[<?!order_conditions>[ ORDER BY 1 OFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY ] ]
```

The `DELETE` clause for `SQLite` with `ORDER BY` and `LIMIT` clause emulation can be described as follows (note how the grammar template is *polymorphic* depending on whether `ORDER BY` and/or `LIMIT` are to be used, else defaults to the simplest `sql` output):

```text
[<?!order_conditions><?!count>
DELETE FROM <from_tables> [, <*from_tables> ] [ WHERE <?where_conditions> ]
][
DELETE FROM <from_tables> [, <*from_tables> ] WHERE rowid IN (
SELECT rowid FROM <from_tables> [, <*from_tables> ]
[ WHERE <?where_conditions> ] ORDER BY <?order_conditions> [, <*order_conditions> ] [ LIMIT <?count> OFFSET <offset|0> ]
)
][<?!order_conditions>
DELETE FROM <from_tables> [, <*from_tables> ] WHERE rowid IN (
SELECT rowid FROM <from_tables> [, <*from_tables> ]
[ WHERE <?where_conditions> ] LIMIT <?count> OFFSET <offset|0>
)
]
```

where `[..]` describe an optional block of `sql code` (depending on passed parameters) and `<..>` describe placeholders for `query` parameters / variables (i.e `non-terminals`).
The optional block of code depends on whether <del>the (first)</del> **all** optional parameters defined inside (with `<?..>` or `<*..>` for rest parameters) exist. Then, that block (and any nested blocks it might contain) is output, else bypassed.


*(for various methods to emulate `LIMIT/OFFSET` clauses see, for example, [here](http://search.cpan.org/~davebaird/SQL-Abstract-Limit-0.12/lib/SQL/Abstract/Limit.pm) and a reasonable critic, mixed with lame advertising self-righteousness, [here](http://blog.jooq.org/2014/06/09/stop-trying-to-emulate-sql-offset-pagination-with-your-in-house-db-framework/))*


`Dialect` will parse this into a (fast) `grammar` template and generate appropriate `sql` output depending on the parameters given automaticaly.


It is very easy, intuitive and powerful to produce `sql code` for an arbitrary `SQL` provider,
by defining the `grammar` of `sql clauses` (sometimes even directly from the `SQL` documentation page, or with only minor adjustments).


The whole point of `Dialect` from the start was to use intuitive configuration to describe `sql` clauses and `sql` normalisation instead of those being hidden behind deep, kludgy and/or cryptic source code abstractions and extensions (plus avoid loading multiple files through interfaces to get the right `sql` emulator). **You** build you own descriptions for `SQL` dialects (and choice of emulations, optimisations, normalisations to use) and **not** the other way around. The library will just try to ease the burden off (some, at least for now) boilerplate code and automate trivial tasks, letting you focus on the important stuff.



**Native SQL functions support**

`Dialect` supports **native SQL functions** (per db vendor) defined in the configuration settings per db vendor and accessed / used genericaly in the `Dialect API` (see text examples)



**Multiple variations of a clause**


`Dialect` supports using multiple variations of the same `SQL` clause, very easily. 

For example, a main `DELETE` clause for `SQLite` with `LIMIT` emulation and another variation (e.g `'delete_with_limit_clause'`) when `SQLite` is configured to allow `LIMIT` clauses in `DELETE` clauses (which is not a default setting out-of-the-box).


User will just pass the clause variation name as parameter to, an otherwise same, `dialect.Delete('delete_with_limit_clause')` method call and `Dialect` will take care of the rest. Easy and flexible as that.

Coupled with the fact that `Dialect` supports `clause` definition via grammar-templates, which are polymoprhic themselves (see above), this is a very powerful and flexible feature.



**Custom Soft Views**

**(experimental feature)**

`Dialect` supports defining custom (soft) `views` which can be used (almost, as of now) like usual `SQL` views.


Reasons to support `soft views` are:


1. `DB` provider does not support `views` by default.
2. User does not have access to create `views` in DB, or does not want to.
3. `Views` are dynamic and/or ad-hoc and it would be overhead to create and drop them all the time.



`Dialect` stores a `sql` definition as a `view` and whenever this soft `view` is used, the actual `sql` definition
is transparently used underneath (with some care for name resolution, selection, re-aliasing, conflicts and so on). 

Soft `Views` are mostly useful for `SELECT` clauses (e.g selecting from a `wordpress` post with associated `meta fields` as if they are one single custom-made table with custom column aliases, this makes code more concise, modular, safer, cleaner and transferable to other DB configurations where indeed a single table can be used and so on)



**Prepared Templates**

**(experimental feature)**

see below `API` examples



###API Reference


* `Dialect` is also a `XPCOM JavaScript Component` (Firefox) (e.g to be used in firefox browser addons/plugins)

```javascript

// -- instance methods --
// --------------------------------------------------------

var dialect = new Dialect( [String vendor="mysql"] );

// NOTE1: all methods are chainable
// NOTE2: sql fields are automaticaly escaped appropriately except if set otherwise
// NOTE3: field values are automaticaly escaped appropriately except if set otherwise
// NOTE4: config sql clauses use 'grammar-like templates' to generate vendor-specific sql code in a flexible and intuitive way

// initiate SELECT directive (resets the instance state to SELECT)
dialect.Select( String | Array fields='*' );

// initiate INSERT directive (resets the instance state to INSERT)
dialect.Insert( String | Array tables, String | Array fields );

// initiate UPDATE directive (resets the instance state to UPDATE)
dialect.Update( String | Array tables );

// initiate DELETE directive (resets the instance state to DELETE)
dialect.Delete( );

// FROM directive
dialect.From( String | Array tables );

// WHERE directive
dialect.Where( String | Object conditions [, String type="AND"] );

// HAVING directive
dialect.Having( String | Object conditions [, String type="AND"] );

// VALUES directive
dialect.Values( Array values );

// SET directive
dialect.Set( Object fields_values );

// JOIN directive
dialect.Join( String table, String | Object condition [, String type=""] );

// GROUP directive
dialect.Group( String field [, String dir="ASC"] );

// ORDER directive
dialect.Order( String field [, String dir="ASC"] );

// LIMIT directive
dialect.Limit( Number count [, Number offset=0] );

// PAGE directive (an alias of LIMIT)
dialect.Page( Number page, Number perpage );

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
    .Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .From('t')
    .Join('t2',{'t.id':'t2.id'},'inner')
    .createView('my_view') // automaticaly clears instance state after view created, so new statements can be used
;

// use it in a SELECT statement
var query_soft_view = dialect
                    .Select('f1 AS f11,f2,f3')
                    .From('my_view')
                    .Where({f1:'2'})
                    .sql()
                    ;

// drop the custom view, if exists
dialect.dropView('my_view');



//
// EXPERIMENTAL FEATURE: 
// Create prepared sql queries as pre-compiled templates (parses sql only once on template creation)

// define/create a prepared sql query template
dialect
    .Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .From('t')
    .Where({
        f1:{eq:'%d:id%',type:'raw'} // NOTE: parameter type format is same as that used in .prepare method above
    })
    .prepareTpl('prepared_query') // automaticaly clears instance state after tpl created, so new statements can be used
;

// or using a ready-made query string also works
dialect.prepareTpl('prepared_query', "SELECT * FROM `table` WHERE `field` = %d:id%");

// use it
// will automaticaly typecast the key to integer (i.e "d:" modifier was used in prepared template definition)
var query_prepared = dialect.prepared('prepared_query',{id:'12'});

// drop the custom prepared sql template, if exists
dialect.dropTpl('prepared_query');
```


###TODO

* add full support for custom soft views [DONE]
* add full support for sql directives (e.g `create table`, `drop table`, `alter table`, `begin transaction`) [DONE PARTIALY]
* add full support for other sql vendors (e.g `Oracle` )



###Performance
