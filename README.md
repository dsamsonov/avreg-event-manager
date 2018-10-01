# avreg-event-manager
Custom event manager for avreg (video registation software)
With telegram messenger support

Как установить
1) установите php-curl
2) импортируйте event-manager.sql в бд avreg (mysql avreg6_db < event-manager.sql )
3) создайте пользователя с правами select на бд avreg и правами select, insert,update, delete на таблицу event-manager
4) положите скрипт в удобное для вас место
5) измените в нем строки
$storage_dir="/var/spool/avreg/"; #директория куда кладутся файлы с записью видео по движению
$dbuser="user";                   #пользователь бд
$dbpass="password";               #пароль
$dbname="avreg6_db";              #бд avreg
$dbhost="127.0.0.1";              #хост на котором работает бд mysql (обычно достаточно оставить дефолтный)
$dbport="3306";                   #порт на котором слушает бд mysql
$tg_token="botTOKEN";             #bot token telegram messenger
$tg_chatid="-chatid";		  #chat id (id чата в который бот будет отправлять сообщения)
6)добавьте в крон примерно следующее задание(отредактировать по своему вкусу)
*/1 *   * * * avreg /usr/bin/php /usr/local/bin/avreg-events.php > /dev/null 2>&1

Добавлена версия на python3
перед запуском надо установить библиотеки
psutil
mysql-connector

pip3 install mysql-connector
pip3 install psutil