import { type NextRequest, NextResponse } from "next/server"

const WORDPRESS_API_URL = "https://noman.ebazarhut.com/wp-json/rail/v1"

export async function GET(request: NextRequest) {
  try {
    const url = `${WORDPRESS_API_URL}/stations`

    console.log("[Next.js API] Proxying stations request to:", url)

    const response = await fetch(url, {
      headers: {
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!response.ok) {
      console.log(`[Next.js API] WordPress API error: ${response.status} - ${response.statusText}`)
      throw new Error(`WordPress API responded with status: ${response.status}`)
    }

    const data = await response.json()
    console.log(`[Next.js API] Raw response:`, data)
    console.log(`[Next.js API] Found ${data.stations?.length || 0} stations`)

    // If no stations from API, return fallback data
    if (!data.stations || data.stations.length === 0) {
      const fallbackData = {
        stations: [
          { title: "Dhaka" },
          { title: "Dhaka Cantonment" },
          { title: "Panchagarh" },
          { title: "Chittagong" },
          { title: "Sylhet" },
          { title: "Rangpur" },
          { title: "Rajshahi" },
          { title: "Khulna" },
          { title: "Barisal" },
          { title: "Mymensingh" },
        ]
      }
      console.log("[Next.js API] Using fallback stations")
      return NextResponse.json(fallbackData)
    }

    return NextResponse.json(data)
  } catch (error) {
    console.error("[Next.js API] Stations error:", error)
    
    // Return fallback data on error
    const fallbackData = {
      stations: [
        { title: "Dhaka" },
        { title: "Dhaka Cantonment" },
        { title: "Panchagarh" },
        { title: "Chittagong" },
        { title: "Sylhet" },
        { title: "Rangpur" },
        { title: "Rajshahi" },
        { title: "Khulna" },
        { title: "Barisal" },
        { title: "Mymensingh" },
      ]
    }
    
    console.log("[Next.js API] Using fallback stations due to error")
    return NextResponse.json(fallbackData)
  }
}
