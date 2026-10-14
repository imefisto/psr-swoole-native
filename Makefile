# Configuration
VERSION ?= $(shell grep -Po '(?<=^## \[)[^\]]+' CHANGELOG.md | head -1)
TAG = v$(VERSION)

# Commands
.PHONY: validate test update release tag push clean

validate:
	composer validate --strict

test:
	# Run your tests here, adjust to your test framework
	vendor/bin/phpunit

update:
	composer update

tag:
	git tag $(TAG)

push:
	git push origin $(TAG)

release: validate test tag push
	@echo "🎉 Release $(TAG) pushed successfully."

clean:
	rm -rf vendor composer.lock
