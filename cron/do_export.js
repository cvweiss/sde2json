'use strict';

const fs = require('fs');

module.exports = {
    exec: f,
    span: 15
}

let complete = false;

async function f(app) {
    if (complete) return;

    if (process.env.publish_dir == undefined) throw 'please define publish_dir in your environment';

    let all_tables = [];

    let tables = await app.mysql.query('show tables');
    for (let row of tables) {
        for (let [key, table] of Object.entries(row)) {
            all_tables.push({name: table, href: process.env.href + table + '.json'});
            console.log('converting ' + table);
            let table_contents = await app.mysql.query('select * from ' + table);
            let arr = [];
            for (let table_row of table_contents) {
                let o = {};
                Object.assign(o, table_row);
                arr.push(o);
            }
            await fs.writeFileSync(process.env.publish_dir + '/' + table + '.json', JSON.stringify(arr));
        }
    }
    await fs.writeFileSync(process.env.publish_dir + '/tables.json', JSON.stringify(all_tables));

    let message = "<html><body>A simple SDE conversion into json files. To see a list of converted tables, see <a href='/tables.json'>tables.json</a><br/>To access a table, visit table-name.json, for example, <a href='/invFlags.json'>invFlags.json</a><br/>Many thanks to FuzzySteve for the SDE conversion into MySQL<br/>Last Updated: " + (new Date())  + "<br/><a href='/installed.md5'>Current MD5</a><br/><br/><small><a href='https://github.com/cvweiss/sde2json/' target='_blank'>Github</a></body></html>";
    await fs.writeFileSync(process.env.publish_dir + '/index.html', message);

    complete = true;
    setTimeout(done, 15000);
}

function done() {
    process.exit();
}
