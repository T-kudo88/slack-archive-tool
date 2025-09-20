import React from 'react';

interface SearchFormProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}

export default function SearchForm({ value, onChange, placeholder }: SearchFormProps) {
  return (
    <input
      type="text"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder || '検索...'}
      className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
    />
  );
}
