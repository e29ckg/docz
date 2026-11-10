# ==============================================================================
# DOCZ Multi-Stage Dockerfile (Pure Node.js & Vue 3 Modern Architecture)
# ==============================================================================

# Stage 1: Build Vue 3 Frontend
FROM node:22-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Stage 2: Build Express TypeScript Server
FROM node:22-alpine AS server-builder
WORKDIR /app/server
COPY server/package*.json ./
RUN npm ci
COPY server/ ./
RUN npm run build

# Stage 3: Production Runner (Lightweight & Secure)
FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3000

# Install production dependencies for server
COPY server/package*.json ./server/
RUN cd server && npm ci --omit=dev && npm cache clean --force

# Copy compiled server code
COPY --from=server-builder /app/server/dist ./server/dist
COPY server/src/db/schema.sql ./server/src/db/schema.sql

# Copy fonts, static assets, and built Vue 3 frontend
COPY web/fonts ./web/fonts
COPY web/favicon.ico ./web/favicon.ico
COPY web/robots.txt ./web/robots.txt
COPY --from=frontend-builder /app/web/app ./web/app

# Create uploads storage directories
RUN mkdir -p web/uploads/docz web/uploads/profile

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:3000/health || exit 1

CMD ["node", "server/dist/index.js"]
