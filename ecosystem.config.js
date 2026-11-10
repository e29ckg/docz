module.exports = {
  apps: [
    {
      name: 'docz-server',
      script: './server/dist/index.js',
      cwd: __dirname,
      instances: 1, // หรือ 'max' สำหรับ multi-core clustering
      exec_mode: 'fork',
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
      },
      env_development: {
        NODE_ENV: 'development',
        PORT: 3000,
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      error_file: './logs/pm2-error.log',
      out_file: './logs/pm2-out.log',
      merge_logs: true,
      time: true,
    },
  ],
};
