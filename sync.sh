cd /Users/jim/Documents/Working/Registry

rsync -avzhe ssh --delete elanregi@elanregistry:/home/elanregi/test/usersc .
rsync -avzhe ssh --delete elanregi@elanregistry:/home/elanregi/test/TODO .
rsync -avzhe ssh --delete --exclude 'userimages' elanregi@elanregistry:/home/elanregi/test/app .
