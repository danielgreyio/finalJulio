#!/bin/bash

# VentDepot Comprehensive Deployment Script
# This script deploys the finalJulio project to both GitHub and Linode production server

# Configuration
LINODE_IP="198.58.124.137"
PROJECT_PATH="/c/xampp/htdocs/finalJulio"
DEPLOY_USER="root"
REMOTE_PATH="/var/www/html"
GITHUB_REPO="https://github.com/Faridak/finalJulio.git"

echo "=== VentDepot Comprehensive Deployment ==="
echo "Deploying to: $DEPLOY_USER@$LINODE_IP:$REMOTE_PATH"
echo "GitHub Repository: $GITHUB_REPO"
echo ""

# Change to project directory
cd "$PROJECT_PATH" || exit 1

# Step 1: Git Operations - Commit and Push to GitHub
echo "=== Step 1: Git Operations ==="
echo "Checking Git status..."
if [[ -n $(git status --porcelain) ]]; then
    echo "Uncommitted changes detected. Adding and committing..."
    git add .
    git commit -m "Automated deployment commit - $(date)"
    if [ $? -eq 0 ]; then
        echo "Changes committed successfully."
    else
        echo "Failed to commit changes."
    fi
else
    echo "No uncommitted changes found."
fi

echo "Pushing to GitHub..."
git push
if [ $? -eq 0 ]; then
    echo "Successfully pushed to GitHub!"
else
    echo "Failed to push to GitHub."
fi

echo ""

# Step 2: Create deployment package
echo "=== Step 2: Creating Deployment Package ==="
echo "Cleaning up previous deployment files..."

# Remove previous deployment files if they exist
rm -rf deploy_temp
rm -f ventdepot-deploy.zip

echo "Creating deployment package..."
mkdir deploy_temp

# Create archive excluding .git and deployment scripts
tar -czf temp_archive.tar.gz --exclude='.git' --exclude='deploy.sh' --exclude='deploy.ps1' --exclude='deploy.bat' --exclude='.gitignore' .

# Extract to deployment directory
tar -xzf temp_archive.tar.gz -C deploy_temp

# Clean up temporary archive
rm -f temp_archive.tar.gz

echo "Deployment package created successfully!"

# Create ZIP archive
echo "Creating ZIP archive..."
if command -v zip >/dev/null 2>&1; then
    zip -r ventdepot-deploy.zip deploy_temp/* >/dev/null
    if [ $? -eq 0 ]; then
        echo "ZIP archive created successfully!"
    else
        echo "Failed to create ZIP archive!"
        exit 1
    fi
else
    echo "zip command not found. Using tar.gz instead..."
    tar -czf ventdepot-deploy.tar.gz deploy_temp/*
    if [ $? -eq 0 ]; then
        echo "tar.gz archive created successfully!"
        ARCHIVE_TYPE="tar.gz"
    else
        echo "Failed to create tar.gz archive!"
        exit 1
    fi
fi

echo ""

# Step 3: Deploy to Linode
echo "=== Step 3: Deploying to Linode ==="

# Check if required tools are available
if command -v scp >/dev/null 2>&1 && command -v ssh >/dev/null 2>&1; then
    echo "SSH tools found. Proceeding with automated deployment..."
    
    # Upload the archive to the Linode server
    echo "Uploading to Linode server..."
    if [ "$ARCHIVE_TYPE" = "tar.gz" ]; then
        scp ventdepot-deploy.tar.gz "$DEPLOY_USER@$LINODE_IP:/tmp/"
        ARCHIVE_FILE="ventdepot-deploy.tar.gz"
    else
        scp ventdepot-deploy.zip "$DEPLOY_USER@$LINODE_IP:/tmp/"
        ARCHIVE_FILE="ventdepot-deploy.zip"
    fi
    
    if [ $? -eq 0 ]; then
        echo "Upload successful!"
        
        # Deploy on remote server
        echo "Deploying on remote server..."
        ssh $DEPLOY_USER@$LINODE_IP << 'ENDSSH'
set -e
REMOTE_PATH="/var/www/html"
ENV_FILE="$REMOTE_PATH/.env"

cd /tmp
if ls ventdepot-deploy.tar.gz >/dev/null 2>&1; then
    tar -xzf ventdepot-deploy.tar.gz
else
    unzip -o ventdepot-deploy.zip
fi
cp -r deploy_temp/* $REMOTE_PATH

# Run database migrations
echo "=== Running migrations ==="
if [ -f "$ENV_FILE" ]; then
    DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | cut -d= -f2 | tr -d '"'"'" )
    DB_NAME=$(grep '^DB_NAME=' "$ENV_FILE" | cut -d= -f2 | tr -d '"'"'" )
    DB_USER=$(grep '^DB_USER=' "$ENV_FILE" | cut -d= -f2 | tr -d '"'"'" )
    DB_PASS=$(grep '^DB_PASS=' "$ENV_FILE" | cut -d= -f2 | tr -d '"'"'" )

    for MIGRATION in $(ls -v "$REMOTE_PATH/migrations/"*.sql 2>/dev/null); do
        echo "  Applying $(basename $MIGRATION)..."
        mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION" && \
            echo "  OK" || echo "  WARNING: $MIGRATION returned errors (may be safe to ignore if idempotent)"
    done
else
    echo "  WARNING: .env not found at $ENV_FILE — skipping migrations. Run them manually."
fi

chown -R www-data:www-data $REMOTE_PATH
chmod -R 755 $REMOTE_PATH
# Protect .env from web access
chmod 640 $REMOTE_PATH/.env 2>/dev/null || true

systemctl reload apache2
echo "Deployment completed at $(date)"
ENDSSH
        
        if [ $? -eq 0 ]; then
            echo "Deployment completed successfully!"
            echo "Your application is now live at: http://$LINODE_IP"
        else
            echo "Remote deployment commands failed!"
        fi
    else
        echo "Upload failed!"
    fi
else
    echo "SSH tools not found. Showing manual deployment instructions..."
    show_manual_instructions
fi

# Function to show manual instructions
show_manual_instructions() {
    echo ""
    echo "=== MANUAL DEPLOYMENT INSTRUCTIONS ==="
    echo "Please follow these steps to manually deploy:"
    echo "1. Make sure you have SSH tools (scp, ssh) installed"
    echo "2. Run this script again"
    echo ""
    echo "Alternatively, you can manually upload and deploy:"
    echo "1. Upload the archive to your Linode server:"
    if [ "$ARCHIVE_TYPE" = "tar.gz" ]; then
        echo "   scp ventdepot-deploy.tar.gz root@$LINODE_IP:/tmp/"
    else
        echo "   scp ventdepot-deploy.zip root@$LINODE_IP:/tmp/"
    fi
    echo "2. SSH into your server:"
    echo "   ssh root@$LINODE_IP"
    echo "3. Run these commands on the server:"
    echo "   cd /tmp"
    if [ "$ARCHIVE_TYPE" = "tar.gz" ]; then
        echo "   tar -xzf ventdepot-deploy.tar.gz"
    else
        echo "   unzip -o ventdepot-deploy.zip"
    fi
    echo "   cp -r deploy_temp/* $REMOTE_PATH"
    echo "   chown -R www-data:www-data $REMOTE_PATH"
    echo "   chmod -R 755 $REMOTE_PATH"
    echo "   systemctl reload apache2"
    echo "   echo 'Deployment completed'"
    echo ""
    echo "Your application will be available at: http://$LINODE_IP"
}

# Cleanup function
cleanup() {
    echo "Cleaning up temporary files..."
    rm -rf deploy_temp
    if [ "$ARCHIVE_TYPE" = "tar.gz" ]; then
        rm -f ventdepot-deploy.tar.gz
    else
        rm -f ventdepot-deploy.zip
    fi
}

# Perform cleanup
cleanup

echo ""
echo "=== Deployment Process Completed ==="
echo "Both GitHub and Linode deployment attempted."