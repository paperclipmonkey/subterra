FROM node:18-alpine

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./
COPY tsconfig.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY src/ ./src/

# Build TypeScript
RUN npm run build

# Expose port 8080 (Cloud Run default)
EXPOSE 8080

# Start the application
CMD ["node", "dist/index.js"]
