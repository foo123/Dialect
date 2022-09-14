Dialect
=======

**Cross-Vendor &amp; Cross-Platform SQL Query Builder for PHP, Python, JavaScript**

![Dialect](/dialect.jpg)

version **1.4.0**


[Etymology of *"dialect"*](http://www.etymonline.com/index.php?term=dialect)


**see also:**

* [ModelView](https://github.com/foo123/modelview.js) a simple, fast, powerful and flexible MVVM framework for JavaScript
* [tico](https://github.com/foo123/tico) a tiny, super-simple MVC framework for PHP
* [LoginManager](https://github.com/foo123/LoginManager) a simple, barebones agnostic login manager for PHP, JavaScript, Python
* [SimpleCaptcha](https://github.com/foo123/simple-captcha) a simple, image-based, mathematical captcha with increasing levels of difficulty for PHP, JavaScript, Python
* [Dromeo](https://github.com/foo123/Dromeo) a flexible, and powerful agnostic router for PHP, JavaScript, Python
* [PublishSubscribe](https://github.com/foo123/PublishSubscribe) a simple and flexible publish-subscribe pattern implementation for PHP, JavaScript, Python
* [Importer](https://github.com/foo123/Importer) simple class &amp; dependency manager and loader for PHP, JavaScript, Python
* [Contemplate](https://github.com/foo123/Contemplate) a fast and versatile isomorphic template engine for PHP, JavaScript, Python
* [HtmlWidget](https://github.com/foo123/HtmlWidget) html widgets, made as simple as possible, both client and server, both desktop and mobile, can be used as (template) plugins and/or standalone for PHP, JavaScript, Python (can be used as [plugins for Contemplate](https://github.com/foo123/Contemplate/blob/master/src/js/plugins/plugins.txt))
* [Paginator](https://github.com/foo123/Paginator)  simple and flexible pagination controls generator for PHP, JavaScript, Python
* [Formal](https://github.com/foo123/Formal) a simple and versatile (Form) Data validation framework based on Rules for PHP, JavaScript, Python
* [Dialect](https://github.com/foo123/Dialect) a cross-vendor &amp; cross-platform SQL Query Builder, based on [GrammarTemplate](https://github.com/foo123/GrammarTemplate), for PHP, JavaScript, Python
* [DialectORM](https://github.com/foo123/DialectORM) an Object-Relational-Mapper (ORM) and Object-Document-Mapper (ODM), based on [Dialect](https://github.com/foo123/Dialect), for PHP, JavaScript, Python
* [Unicache](https://github.com/foo123/Unicache) a simple and flexible agnostic caching framework, supporting various platforms, for PHP, JavaScript, Python
* [Xpresion](https://github.com/foo123/Xpresion) a simple and flexible eXpression parser engine (with custom functions and variables support), based on [GrammarTemplate](https://github.com/foo123/GrammarTemplate), for PHP, JavaScript, Python
* [Regex Analyzer/Composer](https://github.com/foo123/RegexAnalyzer) Regular Expression Analyzer and Composer for PHP, JavaScript, Python



### Contents

* [Requirements](#requirements)
* [DB vendor sql support](#db-vendor-sql-support)
* [Dependencies](#dependencies)
* [Features](#features)
* [API Reference](#api-reference)
* [TODO](#todo)
* [Performance](#performance)


### Requirements

* Support multiple DB vendors (eg. `MySQL`, `MariaDB`, `PostgreSQL`, `SQLite`, `Transact-SQL` (`SQL Server`), ..)
* Easily extended to new `DB`s ( prefereably through a, implementation-independent, config setting )
* Light-weight ( one class/file per implementation if possible )
* Speed
* Modularity and implementation-independent transferability
* Flexible and Intuitive API



### DB vendor sql support

**(complete except for `CREATE` and `ALTER` sql clauses which are only half-complete due to many different vendor-specific parameters)**

1. [`MySQL`](https://dev.mysql.com/doc/refman/5.7/en/)
2. [`MariaDB`](https://mariadb.com/kb/en/sql-statements/)
3. [`PostgreSQL`](https://www.postgresql.org/docs/9.1/reference.html)
4. [`SQLite`](https://www.sqlite.org/lang.html)
5. [`Transact-SQL` (`SQL Server`)](https://msdn.microsoft.com/en-us/library/bb510741.aspx)



### Dependencies

* **PHP: 5.2+**
* **Python: 2.x or 3.x**
* **JavaScript: ES5+**


### Features


**Grammar Templates**

`Dialect` (`v.0.5.0+`) uses a powerful, fast, flexible and intuitive concept: [`grammar templates`](https://github.com/foo123/GrammarTemplate), to configure an `sql` dialect, which is similar to the `SQL` (grammar) documentation format used by `SQL` vendors.


`Dialect` uses a similar *grammar-like* template format, as a **description and generation** tool to produce `sql code` output relevant to a specific `sql` dialect.


For example the `SELECT` clause of `MySql`/`MariaDB` can be modeled / described as follows:

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

The `SELECT` clause for `Transact-SQL` (`SQL Server` 2012+) with `LIMIT` clause emulation can be described as follows:

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
The optional block of code depends on whether **all** optional parameters defined inside (with `<?..>` or `<*..>` for rest parameters) exist. Then, that block (and any nested blocks it might contain) is output, else bypassed.


*(for various methods to emulate `LIMIT/OFFSET` clauses see, for example, [here](http://search.cpan.org/~davebaird/SQL-Abstract-Limit-0.12/lib/SQL/Abstract/Limit.pm) and a reasonable critic [here](http://blog.jooq.org/2014/06/09/stop-trying-to-emulate-sql-offset-pagination-with-your-in-house-db-framework/))*


`Dialect` will parse this into a (fast) `grammar` template and generate appropriate `sql` output depending on the parameters given automaticaly.


It is very easy, intuitive and powerful to produce `sql code` for an arbitrary `SQL` vendor,
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



### API Reference


* `Dialect` is also a `XPCOM JavaScript Component` (Firefox) (e.g to be used in firefox browser addons/plugins)

```javascript

// -- instance methods --
// --------------------------------------------------------

var dialect = new Dialect( [String vendor="mysql"] );

// NOTE1: all methods are chainable
// NOTE2: sql fields are automaticaly escaped appropriately except if set otherwise
// NOTE3: field values are automaticaly escaped appropriately except if set otherwise
// NOTE4: config sql clauses use 'grammar-like templates' to generate vendor-specific sql code in a flexible and intuitive way

// start TRANSACTION directive (resets the instance state to START TRANSACTION)
dialect.StartTransaction( String type=null );

// commit TRANSACTION directive (resets the instance state to COMMIT)
dialect.CommitTransaction( );

// rollback TRANSACTION directive (resets the instance state to ROLLBACK)
dialect.RollbackTransaction( );

// run a complete TRANSACTION directive with included statements and rollback/commit set (resets the instance state to TRANSACTION)
dialect.Transaction( Object options );

// initiate CREATE directive (resets the instance state to CREATE)
dialect.Create( String table[, Object options] ); // NOTE: almost complete

// initiate ALTER directive (resets the instance state to ALTER)
dialect.Alter( String table[, Object options] ); // NOTE: almost complete

// initiate DROP directive (resets the instance state to DROP)
dialect.Drop( String table[, Object options] );

// initiate SELECT directive (resets the instance state to SELECT)
dialect.Select( String | Array fields='*' );

// initiate Union or Union All directive (resets the instance state to UNION)
dialect.Union( Array select_subqueries, all=false );

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

// get sql code (up to this point) as string
// dialect.toString( ) will do same
var sql_code = dialect.sql( );

// set the escaper callback that escapes or quotes strings based on actual DB charsets etc..
// else a default escaper will be used, which may not be optimal based on actual DB charset and so on..
// set second argument to true if db escaper quotes (ie wraps it in quotes) the value as well instead of only escaping it
dialect.escape( Function db_escaper, Boolean does_quoting=false );

// set the escaper callback that escapes or quotes identifiers..
// else a default escaper will be used
// set second argument to true if db escaper quotes (ie wraps it in quotes) the value as well instead of only escaping it
dialect.escapeId( Function db_escaper_id, Boolean does_quoting=false );

// build a subquery on an independent dialect instance with exact same settings
var subquery_sql = dialect.subquery( ).Select('column').From('table').Where({'column':'somevalue'}).sql( );

// prepare a sql_code_string with passed parameters
var prepared_sql = dialect.prepare( String sql_code, Object parameters [, String left_delimiter='%', String right_delimiter='%'] );

// example, will automaticaly typecast the key to integer (i.e "i:" modifier)
var prepared = dialect.prepare("SELECT * FROM `table` WHERE `field` = %i:key%", {key:'12'} );

// available optional modifiers:
// NOTE: any quotes will be added automaticaly,
// quotes, for example for parameters representing strings, should not be added manualy
// r:       raw, pass as is
// l:       typecast to string suitable for a "LIKE" argument with appropriate quotes
// f:       typecast to escaped string or comma-separated list of escaped strings representing identifier, table or field reference(s) with appropriate quotes (see `.escapeId` method above)
// i:       typecast to integer or comma-separated list of integers
// d:       typecast to float or comma-separated list of floats
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


### TODO

* add full support for custom soft views [DONE]
* add support for native sql functions [DONE]
* support `UNION [ALL]` clause [DONE]
* add full support for sql directives (e.g `create table/view`, `drop table/view`, `begin transaction`, `alter table/view`) [ALMOST DONE]
* add support for subqueries [DONE]
* allow general subqueries both as conditions in WHERE clauses ( eg IN ([SUBQUERY]) ) and/or as custom dynamic columns and tables (with aliases) ( eg SELECT column FROM ([SUBQUERY]) AS table) [DONE]
* optimise and generalise grammar-templates abit, use different template per sql clause [DONE]
* add support for other sql vendors (e.g `Oracle`, .. )



### Performance

(TODO)