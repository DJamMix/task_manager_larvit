import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'ru.crewdev.taskmanager',
  appName: 'TaskManager',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    cleartext: true,
  },
  plugins: {
    Keyboard: {
      resize: 'body',
    },
  },
};

export default config;
