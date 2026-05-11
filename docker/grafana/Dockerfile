FROM grafana/grafana:13.0.1

COPY datasources.yml /etc/grafana/provisioning/datasources/
COPY dashboards.yml /etc/grafana/provisioning/dashboards/

COPY dashboards/ /var/lib/grafana/dashboards/