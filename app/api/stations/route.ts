
import { type NextRequest, NextResponse } from "next/server"
import { API_CONFIG } from "@/lib/config"

const WORDPRESS_API_URL = "https://noman.ebazarhut.com/wp-json/rail/v1"

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const search = searchParams.get("search") || ""
    
    const url = new URL(`${WORDPRESS_API_URL}/stations`)
    if (search) {
      url.searchParams.set("search", search)
    }

    console.log("[Next.js API] Proxying stations request to:", url.toString())

    const response = await fetch(url.toString(), {
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

    // Check if we have stations data
    let stations = []
    if (data.stations && Array.isArray(data.stations) && data.stations.length > 0) {
      stations = data.stations
      console.log(`[Next.js API] Found ${stations.length} stations from WordPress`)
    } else {
      // Fallback data
      stations = [
        { id: 1, title: "Dhaka", name: "Dhaka", code: "DHK" },
        { id: 2, title: "Dhaka Cantonment", name: "Dhaka Cantonment", code: "DHKC" },
        { id: 3, title: "Panchagarh", name: "Panchagarh", code: "PCG" },
        { id: 4, title: "Chittagong", name: "Chittagong", code: "CTG" },
        { id: 5, title: "Sylhet", name: "Sylhet", code: "SYL" },
        { id: 6, title: "Rangpur", name: "Rangpur", code: "RNG" },
        { id: 7, title: "Rajshahi", name: "Rajshahi", code: "RAJ" },
        { id: 8, title: "Khulna", name: "Khulna", code: "KHL" },
        { id: 9, title: "Barisal", name: "Barisal", code: "BAR" },
        { id: 10, title: "Mymensingh", name: "Mymensingh", code: "MYM" },
        { id: 11, title: "Comilla", name: "Comilla", code: "COM" },
        { id: 12, title: "Jessore", name: "Jessore", code: "JES" },
        { id: 13, title: "Bogra", name: "Bogra", code: "BOG" },
        { id: 14, title: "Dinajpur", name: "Dinajpur", code: "DNJ" },
        { id: 15, title: "Pabna", name: "Pabna", code: "PAB" },
      ]
      console.log("[Next.js API] Using fallback stations")
    }

    // Ensure consistent format
    const formattedStations = stations.map(station => ({
      id: station.id,
      title: station.title || station.name,
      name: station.name || station.title,
      code: station.code || "",
      slug: station.slug || ""
    }))

    return NextResponse.json({
      stations: formattedStations,
      total: formattedStations.length,
      message: stations.length > 0 ? "Data from WordPress" : "Using fallback data"
    })

  } catch (error) {
    console.error("[Next.js API] Error fetching stations:", error)
    
    // Return fallback data on error
    const fallbackStations = [
      { id: 1, title: "Dhaka", name: "Dhaka", code: "DHK" },
      { id: 2, title: "Dhaka Cantonment", name: "Dhaka Cantonment", code: "DHKC" },
      { id: 3, title: "Panchagarh", name: "Panchagarh", code: "PCG" },
      { id: 4, title: "Chittagong", name: "Chittagong", code: "CTG" },
      { id: 5, title: "Sylhet", name: "Sylhet", code: "SYL" },
      { id: 6, title: "Rangpur", name: "Rangpur", code: "RNG" },
      { id: 7, title: "Rajshahi", name: "Rajshahi", code: "RAJ" },
      { id: 8, title: "Khulna", name: "Khulna", code: "KHL" },
      { id: 9, title: "Barisal", name: "Barisal", code: "BAR" },
      { id: 10, title: "Mymensingh", name: "Mymensingh", code: "MYM" },
    ]

    return NextResponse.json({
      stations: fallbackStations,
      total: fallbackStations.length,
      error: "Failed to fetch from WordPress, using fallback data",
      message: "Using fallback stations due to API error"
    })
  }
}
