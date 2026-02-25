.PHONY: test analyse cs demo alerts k8s-rules image

test:
	vendor/bin/phpunit

analyse:
	vendor/bin/phpstan analyse --no-progress

alerts:
	docker run --rm -v "$(CURDIR)/alerts:/a" -w /a --entrypoint promtool prom/prometheus:v2.55.1 test rules php-fpm.rules.test.yml

k8s-rules:
	python3 -c "rules=open('alerts/php-fpm.rules.yml').read(); body='\n'.join('  '+l if l.strip() else l for l in rules.splitlines()); open('deploy/kubernetes/prometheusrule.yaml','w').write('# Generated from alerts/php-fpm.rules.yml; regenerate with \`make k8s-rules\`.\napiVersion: monitoring.coreos.com/v1\nkind: PrometheusRule\nmetadata:\n  name: php-fpm\nspec:\n' + body + '\n')"

image:
	docker build -t php-fpm-exporter .

demo:
	cd demo && docker compose up -d --build
