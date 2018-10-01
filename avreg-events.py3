#!/usr/bin/env python3
import sys
import os
import mysql.connector
import requests
import urllib
import psutil

storage_dir="/var/spool/avreg/" #directory with avreg video files
dbuser="user"           #database user name
dbpass="password"       #database password
dbname="avreg6_db"              #avreg database
dbhost="127.0.0.1"              #database host
dbport="3306"                   #datavase port
tg_token="botTOKEN"             #telegram bot token
tg_chatid="-ChatID"             #telegram chat id

avregd_pid="/var/run/avreg/avregd.pid"

def tg_sendmessage(message):
   print(message)
   message=urllib.parse.quote(message)
   url="https://api.telegram.org/"+tg_token+"/sendMessage?chat_id="+tg_chatid+"&text="+message;
   r=requests.post(url)
   print(r.status_code, r.reason, r.content)

def tg_sendvideo(message,video):
   url="https://api.telegram.org/"+tg_token+"/sendVideo?chat_id="+tg_chatid
   files={'video': open(video,'rb')}
   data={'caption': message}
   r=requests.post(url, files=files, data=data)
   print(r.status_code, r.reason, r.content)

def fatal_error(error):
    tg_sendmessage("Критическая ошибка: "+error)
    sys.exit(1)

def error(error):
    tg_sendmessage("Ошибка:"+error)

def check_icmp(ip):
    ping=os.system("ping -c 1 -w 2 "+ip+'>/dev/null 2>&1')
    if ping != 0:
        error('Камера недоступна по ICMP: '+ip)	

def db_connect():
    try:
        conn = mysql.connector.connect(
            user=dbuser,
            password=dbpass,
            host=dbhost,
	    port=dbport,
            database=dbname)
        if conn.is_connected():
            return conn
    except mysql.connector.Error as error:
            fatal_error('Ошибка подключения к MySQL: '+str(error))
    
def db_fetchall(conn,query):
    try:
        cursor = conn.cursor()
        cursor.execute(query)
        row = cursor.fetchall()
        return row
    except mysql.connector.Error as error:
        fatal_error('Ошибка в SQL запросе: '+query+' '+str(error))
    finally:
        cursor.close()

def db_update(conn,query):
    try:
        cursor = conn.cursor()
        cursor.execute(query)
        conn.commit()
    except mysql.connector.Error as error:
        fatal_error('Ошибка в SQL запросе: '+query+' '+str(error))
    finally:
        cursor.close()

def check_avregd(avregd_pid):
    try:
          f=open(avregd_pid,'r')
          pid=f.readline()
          f.close()	
    except:
           error("Демон avregd не запущен"); pid=0
    finally:
           if (psutil.pid_exists(int(pid))!=True) and (pid!=0):
                error("Демон avregd не запущен")

check_avregd(avregd_pid)
conn=db_connect()
query=db_fetchall(conn,"SELECT PARVAL FROM CAMERAS WHERE PARNAME='InetCam_IP'")
for ip in query: 
    check_icmp(str(ip[0]))
query=db_fetchall(conn,"SELECT a.CAM_NR,a.DT1,a.DT2,a.EVT_ID,a.EVT_CONT,a.SESS_NR,b.PARVAL FROM EVENTS as a,CAMERAS as b, `events-manager` as c WHERE c.param='last_check_time' AND a.DT1>c.value_date AND a.CAM_NR=b.CAM_NR and b.PARNAME='text_left'")
for row in query: 
    dt1=row[1]; dt2=row[2]
    evt_id=row[3]; evt_cont=str(row[4].decode())
    parval=row[6]
    if evt_id==12:
         tg_sendvideo("Замечено движение на "+parval+" в "+str(dt2),str(storage_dir)+"/"+str(evt_cont))
    if evt_id==1:
         tg_sendmessage("Сообщение демона avregd: "+evt_cont)
    if evt_id==3:
         tg_sendmessage("Проблемы с подключением к камере "+parval+": "+evt_cont)
    if evt_id==22: 
         tg_sendmessage("Изменение качества видеокадра "+parval+": "+evt_cont)
db_update(conn,"UPDATE `events-manager` SET value_date=NOW(), date=NOW() WHERE param='last_check_time'")
conn.close()
