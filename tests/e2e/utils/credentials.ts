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
  /**
   * Optional picker for spec 07 — set via env (do not commit secrets):
   * PW_PICKING_STAFF_EMAIL, PW_PICKING_STAFF_PASSWORD,
   * PW_PICKING_STAFF_FIRSTNAME, PW_PICKING_STAFF_LASTNAME (as in Staff / shift dropdown)
   */
  pickingStaff: {
    email: process.env.PW_PICKING_STAFF_EMAIL || '',
    password: process.env.PW_PICKING_STAFF_PASSWORD || '',
  },
};

