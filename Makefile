COMPOSE := podman-compose
MSGLANGS=$(notdir $(wildcard languages/*.po))
MSGOBJS=$(addprefix languages/,$(MSGLANGS:.po=.mo))

run-server:
	docker-compose -f dev-compose.yaml up

zip:
	zip -r isrp-event-paygate.zip ./ --exclude '.git/*' --exclude '*/.??*' --exclude '.??*' --exclude Makefile --exclude isrp-event-paygate.zip

dev-makepot:
	$(COMPOSE) -f dev-compose.yaml run wpcli --allow-root i18n make-pot wp-content/plugins/isrp-event-paygate/ wp-content/plugins/isrp-event-paygate/isrp-event-paygate.pot

languages/%.mo:
	msgfmt -c -o $@ languages/$*.po

gettext: $(MSGOBJS)

.PHONY: zip dev-makepot gettext
