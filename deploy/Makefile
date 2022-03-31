.PHONY:default

#-----------------
DRY?=1
#-----------------
remote_path="/var/www/filestorage_app"
dryrun=

ifeq ($(DRY),1)
	dryrun = -n
else
	dryrun = 
endif

#-----------------
default:
	rsync -avz $(dryrun) --update --recursive --checksum --files-from=rsync-files.txt --exclude-from=rsync-exclude.txt ../ ${REMOTE_HOST}:$(remote_path)