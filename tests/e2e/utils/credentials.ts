export type UserCreds = {
  email: string;
  password: string;
};

export const creds = {
  admin: {
    email: process.env.PW_ADMIN_EMAIL || 'developer@3ware.mx',
    password: process.env.PW_ADMIN_PASSWORD || 'Dev3loper*999',
  },
  customer: {
    email: process.env.PW_CUSTOMER_EMAIL || 'usuario@3ware.mx',
    password: process.env.PW_CUSTOMER_PASSWORD || 'ramos123',
  },
  modulo1: {
    email: process.env.PW_MOD1_EMAIL || 'modulo1@ramos.mx',
    password: process.env.PW_MOD1_PASSWORD || 'modulo1',
  },
  modulo2: {
    email: process.env.PW_MOD2_EMAIL || 'modulo2@ramos.mx',
    password: process.env.PW_MOD2_PASSWORD || 'modulo2',
  },
  modulo3: {
    email: process.env.PW_MOD3_EMAIL || 'modulo3@ramos.mx',
    password: process.env.PW_MOD3_PASSWORD || 'modulo3',
  },
  modulo4: {
    email: process.env.PW_MOD4_EMAIL || 'modulo4@ramos.mx',
    password: process.env.PW_MOD4_PASSWORD || 'modulo4',
  },
};

