all: clean coverage

list:
	@sh -c "$(MAKE) -p .dummy | awk -F':' '/^[a-zA-Z0-9][^\$$#\/\\t=]*:([^=]|$$)/ {split(\$$1,A,/ /);for(i in A)print A[i]}' | grep -v '__\$$' | sort"

test:
	vendor/bin/phpunit

coverage:
	vendor/bin/phpunit --coverage-html=artifacts/coverage

view-coverage:
	open artifacts/coverage/index.html

clean:
	rm -rf artifacts/*
	rm -rf compiled/*

perf:
	php bin/perf.php

.PHONY: test coverage perf
.dummy:
