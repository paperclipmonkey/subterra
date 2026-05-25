# Build stage
FROM node:24-alpine AS builder

# Install build dependencies for sharp (native module)
RUN apk add --no-cache python3 make g++ vips-dev

# Set working directory
WORKDIR /app

# Copy package files
COPY package.json yarn.lock ./
COPY tsconfig.json ./

# Install ALL dependencies (including devDependencies for TypeScript)
RUN yarn install --frozen-lockfile

# Copy source code
COPY src/ ./src/

# Build TypeScript
RUN yarn build

# Production stage
FROM node:24-alpine

# Install vips runtime for sharp
RUN apk add --no-cache vips

# Set working directory
WORKDIR /app

# Copy package files
COPY package.json yarn.lock ./

# Install only production dependencies
RUN yarn install --frozen-lockfile --production

# Copy built application from builder stage
COPY --from=builder /app/dist ./dist

# Expose port 8080 (Cloud Run default)
EXPOSE 8080

# Start the application
CMD ["node", "dist/index.js"]
