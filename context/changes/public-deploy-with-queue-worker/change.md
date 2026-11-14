---
change_id: public-deploy-with-queue-worker
title: Public deploy on menu.kostecki.dev with queue worker and backups
status: implemented
created: 2026-11-14
updated: 2026-11-14
archived_at: null
---

## Notes

Roadmap F-02 (`context/foundation/roadmap.md`). The roadmap's `kostecki-infra` is the `kostecki-dev-infra` repo (`~/my-projects/kostecki-dev-infra`, GitHub `sebastian-kostecki/kostecki-dev-infra`, default branch `master`); its contract is "each app repo ships `docker-compose.prod.yml` + `Dockerfile.prod` + `scripts/deploy.sh`, Traefik discovers it by labels". Template: root-keeper and household-treasurer production stacks.
