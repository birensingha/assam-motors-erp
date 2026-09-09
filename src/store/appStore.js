import { create } from 'zustand';

const useAppStore = create((set) => ({
  // Auth state
  user: JSON.parse(localStorage.getItem('user')) || null,
  isAuthenticated: !!localStorage.getItem('user'),
  
  setUser: (user) => {
    set({ user, isAuthenticated: !!user });
    if (user) {
      localStorage.setItem('user', JSON.stringify(user));
    } else {
      localStorage.removeItem('user');
    }
  },

  logout: () => {
    set({ user: null, isAuthenticated: false });
    localStorage.removeItem('user');
  },

  // Data state
  customers: JSON.parse(localStorage.getItem('customers')) || [],
  vehicles: JSON.parse(localStorage.getItem('vehicles')) || [],
  jobCards: JSON.parse(localStorage.getItem('jobCards')) || [],
  inventory: JSON.parse(localStorage.getItem('inventory')) || [],
  invoices: JSON.parse(localStorage.getItem('invoices')) || [],
  accounts: JSON.parse(localStorage.getItem('accounts')) || [],

  setCustomers: (customers) => {
    set({ customers });
    localStorage.setItem('customers', JSON.stringify(customers));
  },

  setVehicles: (vehicles) => {
    set({ vehicles });
    localStorage.setItem('vehicles', JSON.stringify(vehicles));
  },

  setJobCards: (jobCards) => {
    set({ jobCards });
    localStorage.setItem('jobCards', JSON.stringify(jobCards));
  },

  setInventory: (inventory) => {
    set({ inventory });
    localStorage.setItem('inventory', JSON.stringify(inventory));
  },

  setInvoices: (invoices) => {
    set({ invoices });
    localStorage.setItem('invoices', JSON.stringify(invoices));
  },

  setAccounts: (accounts) => {
    set({ accounts });
    localStorage.setItem('accounts', JSON.stringify(accounts));
  },
}));

export default useAppStore;