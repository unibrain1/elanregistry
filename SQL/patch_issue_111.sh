#  UPDATE `cars` SET `ctime` = '2004-01-22 18:00:39' WHERE `cars`.`id` = 3

IFS=,

while read id date
do
	echo "UPDATE cars SET ctime = '${date}' WHERE cars.id = ${id};"

done < patch_issue_111.csv
