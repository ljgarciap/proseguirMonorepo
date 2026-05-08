const fs = require('fs');
const path = require('path');

// Basic .env parser to avoid external dependencies
const parseEnv = (filePath) => {
    if (!fs.existsSync(filePath)) return {};
    const content = fs.readFileSync(filePath, 'utf8');
    return content.split('\n').reduce((acc, line) => {
        const [key, ...valueParts] = line.split('=');
        if (key && valueParts.length > 0) {
            acc[key.trim()] = valueParts.join('=').trim();
        }
        return acc;
    }, {});
};

const envVars = parseEnv(path.resolve(__dirname, '.env'));
const envDirectory = './src/environments';

if (!fs.existsSync(envDirectory)) {
    fs.mkdirSync(envDirectory, { recursive: true });
}

const targetPath = `./src/environments/environment.ts`;
const targetPathDev = `./src/environments/environment.development.ts`;

const envConfigFile = `export const environment = {
  production: ${envVars.PRODUCTION === 'true'},
  apiUrl: '${envVars.API_URL || 'http://localhost:8000/api'}',
  n8nWebhookUrl: '${envVars.N8N_WEBHOOK_URL || 'http://localhost:5678/webhook/factoring-upload'}'
};
`;

console.log('Generando archivos de entorno...');

fs.writeFileSync(targetPath, envConfigFile);
fs.writeFileSync(targetPathDev, envConfigFile);

console.log(`Entorno generado con éxito.`);
