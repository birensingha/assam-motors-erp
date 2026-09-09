import React from 'react';
import { Bell, Search, User } from 'lucide-react';

const Header = ({ user }) => {
  return (
    <header className="bg-white border-b border-neutral-200 shadow-sm sticky top-0 z-30">
      <div className="px-6 py-4 flex items-center justify-between">
        {/* Search */}
        <div className="flex-1 max-w-md">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-400" size={20} />
            <input
              type="text"
              placeholder="Search customers, vehicles, invoices..."
              className="w-full pl-10 pr-4 py-2.5 bg-neutral-50 border border-neutral-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white"
            />
          </div>
        </div>

        {/* Right section */}
        <div className="flex items-center gap-4 ml-6">
          {/* Notifications */}
          <button className="relative p-2 text-neutral-600 hover:bg-neutral-100 rounded-lg transition-colors">
            <Bell size={20} />
            <span className="absolute top-1 right-1 w-2 h-2 bg-danger-600 rounded-full"></span>
          </button>

          {/* User profile */}
          <div className="flex items-center gap-3 pl-4 border-l border-neutral-200">
            <div className="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
              <User size={20} className="text-primary-600" />
            </div>
            <div className="hidden sm:block">
              <p className="text-sm font-medium text-neutral-900">{user?.name || 'User'}</p>
              <p className="text-xs text-neutral-500">{user?.email || 'user@assam-motors.com'}</p>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;