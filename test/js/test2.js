var Dialect = require("../../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect('mysql');

echo(dialect.clear().Create('new_table', {
    ifnotexists: true,
    columns: [
        {column:'id', type:dialect.sql_type('bigint',[20]), isnotnull:1, auto_increment:1},
        {column:'name', type:dialect.sql_type('varchar',[100]), isnotnull:1, default_value:"''"},
        {column:'categoryid', type:dialect.sql_type('bigint',[20]), isnotnull:1, default_value:0},
        {column:'companyid', type:dialect.sql_type('bigint',[20]), isnotnull:1, default_value:0},
        {column:'fields', type:dialect.sql_type('text'), isnotnull:1, default_value:"''"},
        {column:'start', type:dialect.sql_type('datetime'), isnotnull:1, default_value:"'0000-00-00 00:00:00'"},
        {column:'end', type:dialect.sql_type('datetime'), isnotnull:1, default_value:"'0000-00-00 00:00:00'"},
        {column:'status', type:dialect.sql_type('smallint',[8]), isnotnull:1, default_value:0},
        {column:'extra', type:dialect.sql_type('text'), isnotnull:1, default_value:"''"},
        {key:['categoryid'], name:'categoryid'},
        {key:['companyid'], name:'companyid'},
        {uniquekey:['id'], name:'id', constraint:'constraint_name'}
    ],
    table: [
        {collation:'utf8_general_ci'}
    ]
}).sql());

echo();

echo(dialect.clear().Create('new_view', {
    view: true,
    ifnotexists: true,
    columns: ['id', 'name'],
    query: 'SELECT id, name FROM another_table'
}).sql());

