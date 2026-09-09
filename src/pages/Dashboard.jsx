import React from 'react';
import { TrendingUp, Users, FileText, Package, DollarSign, AlertCircle } from 'lucide-react';
import useAppStore from '../store/appStore';

const Dashboard = () => {
  const customers = useAppStore((state) => state.customers);
  const invoices = useAppStore((state) => state.invoices);
  const inventory = useAppStore((state) => state.inventory);

  const stats = [
    {
      title: 'Total Customers',
      value: customers.length,
      icon: Users,
      color: 'bg-blue-100 text-blue-600',
      trend: '+12.5%',
    },
    {
      title: 'Total Invoices',
      value: invoices.length,
      icon: FileText,
      color: 'bg-green-100 text-green-600',
      trend: '+8.2%',
    },
    {
      title: 'Inventory Items',
      value: inventory.length,
      icon: Package,
      color: 'bg-yellow-100 text-yellow-600',
      trend: '+5.0%',
    },
    {
      title: 'Total Revenue',
      value: `₹${(invoices.reduce((sum, inv) => sum + (inv.total || 0), 0)).toLocaleString()}`,
      icon: DollarSign,
      color: 'bg-purple-100 text-purple-600',
      trend: '+23.1%',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-neutral-900">Dashboard</h1>
        <p className="text-neutral-600 mt-1">Welcome to Assam Motors ERP System</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat) => {
          const Icon = stat.icon;
          return (
            <div key={stat.title} className="card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-neutral-600 text-sm font-medium">{stat.title}</p>
                  <p className="text-3xl font-bold text-neutral-900 mt-2">{stat.value}</p>
                  <p className="text-green-600 text-sm mt-2 flex items-center gap-1">
                    <TrendingUp size={16} />
                    {stat.trend} from last month
                  </p>
                </div>
                <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${stat.color}`}>
                  <Icon size={24} />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 card p-6">
          <h2 className="text-lg font-bold text-neutral-900 mb-4">Recent Invoices</h2>
          <div className="space-y-3">
            {invoices.slice(0, 5).map((invoice) => (
              <div key={invoice.id} className="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                <div>
                  <p className="font-medium text-neutral-900">{invoice.id}</p>
                  <p className="text-sm text-neutral-600">{invoice.date}</p>
                </div>
                <p className="font-bold text-neutral-900">₹{invoice.total?.toLocaleString()}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="card p-6 bg-gradient-to-br from-yellow-50 to-orange-50">
          <div className="flex items-center gap-3 mb-4">
            <AlertCircle className="text-orange-600" size={24} />
            <h2 className="text-lg font-bold text-neutral-900">Low Stock Alert</h2>
          </div>
          <div className="space-y-2">
            {inventory.filter((item) => (item.stock || 0) < 5).map((item) => (
              <div key={item.id} className="p-2 bg-white rounded">
                <p className="text-sm font-medium text-neutral-900">{item.name}</p>
                <p className="text-xs text-orange-600">Stock: {item.stock}</p>
              </div>
            ))}
            {inventory.filter((item) => (item.stock || 0) < 5).length === 0 && (
              <p className="text-sm text-neutral-600">All items have good stock levels</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;