
import { type NextRequest, NextResponse } from "next/server"

const WORDPRESS_API_URL = "https://noman.ebazarhut.com/wp-json/rail/v1"

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const from = searchParams.get("from")
    const to = searchParams.get("to")
    const query = searchParams.get("query")
    const routeCode = searchParams.get("routeCode")

    let wpUrl: URL

    if (routeCode) {
      // Search by route code
      wpUrl = new URL(`${WORDPRESS_API_URL}/trains/route/${routeCode}`)
      console.log(`[Next.js API] Proxying route code search to: ${wpUrl.toString()}`)
    } else if (from && to) {
      // Route-based search
      wpUrl = new URL(`${WORDPRESS_API_URL}/trains/search`)
      wpUrl.searchParams.set("from", from)
      wpUrl.searchParams.set("to", to)
      console.log(`[Next.js API] Proxying train search to: ${wpUrl.toString()}`)
    } else if (query) {
      // Train name/number search
      wpUrl = new URL(`${WORDPRESS_API_URL}/trains/search`)
      wpUrl.searchParams.set("query", query)
      console.log(`[Next.js API] Proxying train search to: ${wpUrl.toString()}`)
    } else {
      // List all trains
      wpUrl = new URL(`${WORDPRESS_API_URL}/trains`)
      console.log(`[Next.js API] Proxying train list to: ${wpUrl.toString()}`)
    }

    const response = await fetch(wpUrl.toString(), {
      headers: {
        "Content-Type": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!response.ok) {
      console.log(`[Next.js API] WordPress API error: ${response.status}`)
      
      // Return sample data when WordPress API fails
      const sampleTrains = [
        {
          id: "101",
          name: "Suborna Express",
          number: "101",
          from_station: "Dhaka",
          to_station: "Chittagong",
        },
        {
          id: "102",
          name: "Turna Nishitha",
          number: "102",
          from_station: "Chittagong",
          to_station: "Dhaka",
        },
        {
          id: "103",
          name: "Intercity Express",
          number: "103",
          from_station: "Dhaka",
          to_station: "Sylhet",
        },
        {
          id: "104",
          name: "Parabat Express",
          number: "104",
          from_station: "Sylhet",
          to_station: "Dhaka",
        },
        {
          id: "105",
          name: "Rangpur Express",
          number: "105",
          from_station: "Dhaka",
          to_station: "Rangpur",
        },
      ]

      // Filter sample data based on search criteria
      let filteredTrains = sampleTrains
      
      if (from && to) {
        filteredTrains = sampleTrains.filter(
          train => 
            train.from_station.toLowerCase().includes(from.toLowerCase()) &&
            train.to_station.toLowerCase().includes(to.toLowerCase())
        )
      } else if (query) {
        filteredTrains = sampleTrains.filter(
          train => 
            train.name.toLowerCase().includes(query.toLowerCase()) ||
            train.number.includes(query)
        )
      }

      return NextResponse.json({
        trains: filteredTrains,
        total: filteredTrains.length,
        message: "Using sample data - WordPress API unavailable"
      })
    }

    const data = await response.json()
    console.log(`[Next.js API] WordPress response:`, data)

    // Handle the response format from WordPress
    let trains = []
    if (data.trains && Array.isArray(data.trains)) {
      trains = data.trains
    } else if (data.items && Array.isArray(data.items)) {
      trains = data.items
    } else if (Array.isArray(data)) {
      trains = data
    }

    // Process trains to ensure correct numbering based on search direction
    const processedTrains = trains.map((train: any) => ({
      ...train,
      // Ensure we pass through the route-specific codes
      code_from_to: train.code_from_to,
      code_to_from: train.code_to_from,
      // For route searches, determine if we need to swap the display
      display_from: from || train.from_station,
      display_to: to || train.to_station
    }))

    return NextResponse.json({
      trains: processedTrains,
      total: data.total || trains.length,
      message: "Data from WordPress"
    })

  } catch (error) {
    console.error("[Next.js API] Train search error:", error)

    // Return sample trains on error
    const sampleTrains = [
      {
        id: "101",
        name: "Suborna Express",
        number: "101",
        from_station: "Dhaka",
        to_station: "Chittagong",
      },
      {
        id: "102",
        name: "Turna Nishitha", 
        number: "102",
        from_station: "Chittagong",
        to_station: "Dhaka",
      },
      {
        id: "103",
        name: "Intercity Express",
        number: "103", 
        from_station: "Dhaka",
        to_station: "Sylhet",
      },
    ]

    return NextResponse.json({
      trains: sampleTrains,
      total: sampleTrains.length,
      error: "WordPress API unavailable",
      message: "Using sample data"
    })
  }
}
