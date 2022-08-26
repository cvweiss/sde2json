# sde2json
Simple FuzzySteve MySQL SDE to JSON conversion.

### Requirements
node, npm, mysql, redis

### Setup

After your ```git checkout cvweiss/sde2json``` modify .env to your setup using the example.env file.

Install requirements: ```npm update```

Run ```update.sh``` in the sde directory.

If you'd like automated updates setup the cronjob (configure to your system, this is mine)

    0       *       *       *       *       ~/sde.zzeve.com/sde/update.sh

Make sure your apache/nginx/whatever points to the public directory. 
