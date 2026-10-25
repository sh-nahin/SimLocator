import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "SimLocator",
  description: "Register your mobile number and area to see your SIM operator.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen text-foreground antialiased bg-amber-50">
        <header className="mx-auto flex  items-center justify-between px-15 py-5 bg-amber-100 text-2xl">
          <Link href="/" className="text-lg font-semibold">Find Your Operator</Link>
          <nav className="flex gap-5 text-sm bg ">
            <Link href="/" className="hover:underline">Register</Link>
            <Link href="/login" className="hover:underline">Log in</Link>
            <Link href="/admin" className="hover:underline">Admin</Link>
          </nav>
        </header>
        <main className="mx-auto max-w-5xl px-4 pb-16">{children}</main>
      </body>
    </html>
  );
}
