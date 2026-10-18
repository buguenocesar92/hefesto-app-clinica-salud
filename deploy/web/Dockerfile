# =============================================================================
# Hefesto Studio — ClinicaSalud: imagen de la interfaz
# =============================================================================
# Node 24 sobre Alpine. La version cumple los motores que Nuxt 4.6.2 declara:
# node ^22.22.3 || ^24.15.0 || >=26.
#
# Compila en la etapa de construccion y se lleva solo el resultado al runtime,
# como en las demas aplicaciones de Hefesto.
# =============================================================================

FROM node:24-alpine3.24 AS construccion

WORKDIR /app

# Dependencias primero: la capa se reutiliza mientras el lock no cambie.
COPY web/package.json web/package-lock.json* ./

RUN npm ci --no-audit --no-fund

COPY web/ ./

# `NUXT_API_BASE` se compila como variable publica de Nuxt si se declara en
# runtimeConfig.public. Aqui solo se necesita para la construccion; el valor
# real llega como variable de entorno en ejecucion.
RUN npm run build

# -----------------------------------------------------------------------------
FROM node:24-alpine3.24 AS runtime

WORKDIR /app

ENV NODE_ENV=production \
    NITRO_HOST=0.0.0.0 \
    NITRO_PORT=3000

COPY --from=construccion /app/.output ./.output

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:3000/login').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", ".output/server/index.mjs"]