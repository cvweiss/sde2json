# sde2json
Simple FuzzySteve MySQL SDE to JSON conversion.

### Setup

After your ```git checkout cvweiss/sde2json``` modify config.json to your setup.

Run ```update.sh``` in the sde directory.

If you'd like automated updates setup the cronjob (configure to your system, this is mine)

    0       *       *       *       *       ~/sde.zzeve.com/sde/update.sh

Make sure your apache/nginx/whatever points to the public directory. 
