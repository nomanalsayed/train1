import type { Metadata, Viewport } from "next"
import { Inter } from "next/font/google"
import "./globals.css"
import { ThemeProvider } from "@/components/theme-provider"

const inter = Inter({ subsets: ["latin"] })

export const metadata: Metadata = {
  title: "Bangladesh Railway Seat Guide",
  description: "Find forward-facing seats on Bangladesh Railway trains with our smart seat direction guide",
  keywords: ["Bangladesh Railway", "train seats", "forward-facing", "seat guide", "railway booking"],
  authors: [{ name: "Railway Seat Guide Team" }],
  creator: "Railway Seat Guide",
  publisher: "Railway Seat Guide",
  robots: "index, follow",
  openGraph: {
    title: "Bangladesh Railway Seat Guide",
    description: "Smart seat direction guide for Bangladesh Railway",
    type: "website",
    locale: "en_US",
  },
  twitter: {
    card: "summary_large_image",
    title: "Bangladesh Railway Seat Guide",
    description: "Find the best forward-facing seats on your train journey",
  },
    generator: 'v0.app'
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  userScalable: false,
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#10b981" },
    { media: "(prefers-color-scheme: dark)", color: "#059669" },
  ],
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <link rel="manifest" href="/manifest.json" />
        <link rel="apple-touch-icon" href="/placeholder-logo.png" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="format-detection" content="telephone=no" />
      </head>
      <body className={`${inter.className} antialiased`}>
        <ThemeProvider
          attribute="class"
          defaultTheme="light"
          enableSystem={false}
          disableTransitionOnChange
        >
          <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-blue-50">
            {children}
          </div>
        </ThemeProvider>
      </body>
    </html>
  )
}
