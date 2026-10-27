# Production Dockerfile for Nunno WhatsApp Bridge (24/7 Cloud Hosting)
FROM node:22-slim

# Install system dependencies needed for Headless Chromium
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget \
    gnupg \
    ca-certificates \
    procps \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    fonts-liberation \
    chromium \
    && rm -rf /var/lib/apt/lists/*

# Point Puppeteer and WhatsApp Web to the installed Chromium
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    WHATSAPP_BROWSER_PATH=/usr/bin/chromium \
    NODE_ENV=production

WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev

COPY . .

# Persistent session data mount point
VOLUME ["/app/../data/whatsapp-bridge"]

EXPOSE 3210

CMD ["node", "server.js"]
